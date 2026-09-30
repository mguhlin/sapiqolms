<?php
// Serves course static files (reader, images, captions, video) from the courses/
// project directory over the same origin as the LMS, with HTTP range support so
// video seeking works. Because sapiqo/ and sapiqo-data/ live inside the same
// courses/ folder, this passthrough refuses those paths so the database, config,
// and code are never web-reachable.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/quiz.php';
require_once __DIR__ . '/editor.php';
require_once __DIR__ . '/creator.php';
require_once __DIR__ . '/scorm.php';

const SERVE_DENY_TOP = ['sapiqo', 'sapiqo-data'];

const SERVE_TYPES = [
    'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
    'mjs' => 'text/javascript; charset=utf-8', 'json' => 'application/json; charset=utf-8',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
    'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
    'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
    'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg',
    'vtt' => 'text/vtt', 'srt' => 'application/x-subrip; charset=utf-8',
    'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf',
    'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8',
];

// $rel is the URL path after "/courses/". Serves the file or sends a 4xx.
function serve_course(string $rel): void {
    // $rel is already URL-decoded by the front controller.
    $base = realpath(rtrim(lms_config()['courses_dir'], '/'));
    if ($base === false) { http_response_code(404); exit; }

    // Reject traversal and the private LMS folders.
    $rel = ltrim($rel, '/');
    $top = strtolower(explode('/', $rel)[0] ?? '');
    if ($rel === '' || str_contains($rel, '..') || in_array($top, SERVE_DENY_TOP, true) || $top[0] === '.') {
        http_response_code(403); exit('Forbidden');
    }

    $full = realpath($base . '/' . $rel);
    if ($full === false) { http_response_code(404); exit; }
    // Ensure the resolved path stays inside the courses directory.
    if (!str_starts_with($full, $base . DIRECTORY_SEPARATOR) && $full !== $base) { http_response_code(403); exit('Forbidden'); }
    if (is_dir($full)) {
        $full = rtrim($full, '/') . '/index.html';
        if (!is_file($full)) { http_response_code(404); exit; }
    }
    if (!is_file($full)) { http_response_code(404); exit; }

    // Shared reader assets are public. Course shells show only a preview to
    // visitors; every other course file is protected by the same access rules.
    $authed = function_exists('is_logged_in') && is_logged_in();
    $seg = explode('/', $rel);
    $isCourseJson = count($seg) === 2 && $seg[1] === 'course.json';
    $isShell = count($seg) <= 2 && basename($full) === 'index.html';
    $isMedia = in_array('media', $seg, true) || in_array('scorm', $seg, true);
    if ($top !== 'assets') {
        $course = course_by_slug($seg[0]);
        if (!$course) { http_response_code(404); exit; }
        $previewer = $authed && can_edit_content();
        if (!$previewer && (!(int) $course['active'] || ($course['status'] ?? 'published') === 'draft')) {
            http_response_code(403); exit('Course is unavailable.');
        }
        if (!$authed) {
            if ($isCourseJson) {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                echo json_encode(course_preview($full), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$isShell) { http_response_code(403); exit('Sign in to view course content.'); }
        } else {
            $error = course_access_error((int) current_user()['id'], $course, true);
            if ($error !== null) { http_response_code(403); exit($error); }
            // Sources and arbitrary package files never expose raw answer keys.
            if (!$previewer && in_array(strtolower(pathinfo($full, PATHINFO_EXTENSION)), ['md', 'json', 'php', 'phtml'], true)
                && !$isCourseJson) { http_response_code(403); exit('Source download requires authoring access.'); }
            if ($isCourseJson) {
                $data = course_settings_overlay(json_decode((string) file_get_contents($full), true) ?: [], $seg[0]);
                if (!$previewer) $data = quiz_prepare_course($data, (int) $course['id']);
                $data = sanitize_course_html($data);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                echo json_encode($previewer ? $data : strip_quiz_answers($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    // Never execute a package-supplied root shell in the privileged LMS origin.
    if ($top !== 'assets' && $isShell) {
        $data = course_load_json($course['slug']);
        header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: private, no-store');
        if (($data['type'] ?? '') === 'scorm') {
            if (!$authed) { http_response_code(403); exit('Sign in to launch this package.'); }
            echo scorm_player_html($data);
        } else { echo cr_shell_html($data['title'] ?? $course['title'], $data['tagline'] ?? '', true); }
        exit;
    }

    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $type = SERVE_TYPES[$ext] ?? 'application/octet-stream';
    $size = filesize($full);

    // All session-derived decisions (auth gating above) are made; release the
    // session file lock now so this streaming response — often many concurrent
    // media/image requests from one learner — doesn't serialize behind the same
    // user's other in-flight requests. Big win for perceived speed at scale.
    if (function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header('Content-Type: ' . $type);
    header('Accept-Ranges: bytes');
    header('Cache-Control: ' . ($top === 'assets' ? 'public, max-age=3600' : 'private, no-store'));
    header('X-Content-Type-Options: nosniff');
    // SVG (and uploaded HTML/XML under media/) can carry scripts that would run in
    // our origin if opened directly (stored XSS). Sandbox them so no script can
    // execute — they still render. The reader shell (course-root index.html) is
    // NOT sandboxed, so only media HTML is covered by the $isMedia guard.
    $sandbox = in_array($ext, ['svg', 'svgz'], true)
        || ($top !== 'assets' && in_array($ext, ['html', 'htm', 'xhtml', 'xml'], true));
    if ($sandbox) {
        if (in_array('scorm', $seg, true) && in_array($ext, ['html','htm'], true)) {
            // Opaque origin: scripts can run, but cannot read LMS DOM/storage/API.
            // Every entry point remains sandboxed even when opened directly.
            header("Content-Security-Policy: sandbox allow-scripts; default-src https: http: data: blob:; script-src https: http: 'unsafe-inline' 'unsafe-eval'; style-src https: http: 'unsafe-inline'; connect-src 'none'; object-src 'none'; form-action 'none'; base-uri 'none'");
            $html = (string)file_get_contents($full);
            $bridge = '<script src="' . e(url('/assets/js/scorm-content.js')) . '"></script>';
            // Run the API shim before package scripts, without allowing package head/base directives to replace it.
            echo $bridge . $html;
            exit;
        }
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; sandbox");
    }

    // --- Optional web-server offload (X-Sendfile / X-Accel-Redirect) ----------
    // Serving large media through PHP's readfile ties up a PHP-FPM worker for the
    // whole transfer. When configured, hand the actual byte-pushing to the web
    // server (which does it far more efficiently and frees the worker at once),
    // keeping the auth + path checks above in PHP. We never offload the sandboxed
    // types, so their protective CSP header is always applied by PHP.
    $cfg = lms_config();
    if (!$sandbox && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        // nginx: internal location aliased to the courses dir (see DEPLOYMENT.md).
        $accel = (string) ($cfg['x_accel_redirect'] ?? '');
        if ($accel !== '') {
            header('X-Accel-Redirect: ' . rtrim($accel, '/') . '/' . $rel);
            exit;
        }
        // Apache/lighttpd with mod_xsendfile: hand off the absolute filesystem path.
        if (!empty($cfg['x_sendfile'])) {
            header('X-Sendfile: ' . $full);
            exit;
        }
    }

    // Range request (video seeking / resumable downloads).
    $range = $_SERVER['HTTP_RANGE'] ?? '';
    if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m)) {
        $start = $m[1] === '' ? max(0, $size - (int) $m[2]) : (int) $m[1];
        $end   = $m[1] === '' || $m[2] === '' ? $size - 1 : (int) $m[2];
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        $end = min($end, $size - 1);
        $len = $end - $start + 1;
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
        header('Content-Length: ' . $len);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
        _serve_stream($full, $start, $len);
    } else {
        header('Content-Length: ' . $size);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
        readfile($full);
    }
    exit;
}

// Overlay per-course settings (stored in the DB, editable in Admin) onto the
// served course.json so the reader can honor them — e.g. sequential mode, which
// locks each lesson until the previous one is complete.
function course_settings_overlay(array $data, string $slug): array {
    if ($slug === '' || !function_exists('course_by_slug')) return $data;
    $row = course_by_slug($slug);
    if (!$row) return $data;
    $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
    $settings['sequential'] = course_is_sequential($row);
    $settings['assignment_count'] = count(assignment_list((int)$row['id']));
    $settings['required_assignments'] = count(array_filter(assignment_list((int)$row['id']), fn($a)=>!empty($a['required_completion'])));
    $data['settings'] = $settings;
    return $data;
}

// Build a public-safe syllabus preview of a course.json: module #, title, and a
// description, plus lesson titles — but NO lesson/topic content and no video refs.
function course_preview(string $courseJsonPath): array {
    $data = json_decode((string) file_get_contents($courseJsonPath), true) ?: [];
    $modules = [];
    foreach ($data['modules'] ?? [] as $m) {
        $summary = $m['summary'] ?? '';
        if ($summary === '') $summary = _first_paragraph($m['lessons'][0]['content'] ?? '');
        $lessons = [];
        foreach ($m['lessons'] ?? [] as $l) {
            $lessons[] = ['id' => $l['id'] ?? '', 'title' => $l['title'] ?? '', 'slug' => $l['slug'] ?? ''];
        }
        $modules[] = [
            'id' => $m['id'] ?? '', 'key' => $m['key'] ?? '', 'label' => $m['label'] ?? '',
            'title' => $m['title'] ?? '', 'summary' => editor_sanitize_html($summary), 'lessons' => $lessons,
        ];
    }
    return [
        'slug' => $data['slug'] ?? '', 'title' => $data['title'] ?? '',
        'provider' => $data['provider'] ?? '', 'tagline' => $data['tagline'] ?? '',
        'tags' => $data['tags'] ?? [], 'stats' => $data['stats'] ?? [],
        'preview' => true, 'modules' => $modules,
    ];
}

// Remove the `correct` flag from every quiz option so learners can't read the
// answer key from the served course.json. Grading is done server-side.
function strip_quiz_answers(array $data): array {
    if (empty($data['modules']) || !is_array($data['modules'])) return $data;
    foreach ($data['modules'] as $mi => $m) {
        if (empty($m['lessons']) || !is_array($m['lessons'])) continue;
        foreach ($m['lessons'] as $li => $l) {
            if (empty($l['quiz']['questions']) || !is_array($l['quiz']['questions'])) continue;
            foreach ($l['quiz']['questions'] as $qi => $q) {
                $path = &$data['modules'][$mi]['lessons'][$li]['quiz']['questions'][$qi];
                foreach (($q['options'] ?? []) as $oi => $o) {
                    unset($path['options'][$oi]['correct']);   // choice answer key
                }
                unset($path['answers']);   // short-answer accepted answers
                unset($path['feedback']);  // returned via grading response instead
                unset($path);
            }
        }
    }
    return $data;
}

function _first_paragraph(string $html): string {
    if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $m)) {
        $t = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        if ($t !== '') return mb_strlen($t) > 220 ? mb_substr($t, 0, 217) . '…' : $t;
    }
    return '';
}

function _serve_stream(string $file, int $start, int $len): void {
    $fh = fopen($file, 'rb');
    if (!$fh) { http_response_code(500); exit; }
    fseek($fh, $start);
    $chunk = 1 << 20; // 1 MiB
    while ($len > 0 && !feof($fh)) {
        $read = fread($fh, (int) min($chunk, $len));
        if ($read === false) break;
        echo $read;
        $len -= strlen($read);
        flush();
    }
    fclose($fh);
}

// Imports and legacy topics go through the same sanitizer as edited blocks.
function sanitize_course_html(array $data): array {
    foreach ($data as $key => $value) {
        if (is_array($value)) $data[$key] = sanitize_course_html($value);
        elseif (is_string($value) && in_array($key, ['content', 'html', 'overview_html', 'summary'], true)) {
            $data[$key] = editor_sanitize_html($value);
        }
    }
    return $data;
}
