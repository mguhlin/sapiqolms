<?php
// LearnDash importer: convert a LearnDash/Gutenberg course export (.json) into a
// native Sapiqo course (content/<slug>/course.json + reader shell). This is the
// browser-based, dependency-free port of scripts/build.py — no Python needed.
//
// It preserves lesson text, images, and video embeds, only stripping WordPress
// Gutenberg block comments and rewriting Vimeo embeds into responsive iframes
// (the original Vimeo link is kept in each lesson's videos[]). Images can be
// mirrored locally (SSRF-guarded via safe_fetch) so the course is self-contained.
//
// Videos: a JSON export references videos on Vimeo, not files, so imported
// courses embed Vimeo. To self-host MP4s, drop the files in
// content/<slug>/media/videos/ and add a videos.json map, then rescan (see
// scripts/build.py and README).

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Entry point. Returns [bool ok, string slug|error, array report].
function import_learndash(string $tmpFile, string $origName, bool $mirrorImages = true): array {
    $raw = @file_get_contents($tmpFile);
    if ($raw === false || $raw === '') return [false, 'Could not read the uploaded file.', []];
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['courses'][0]) || !isset($data['lessons'])) {
        return [false, 'That does not look like a LearnDash export (expected "courses", "lessons", "topics").', []];
    }
    $course = $data['courses'][0];
    $lessonsRaw = $data['lessons'] ?? [];
    $topicsRaw = $data['topics'] ?? [];

    $title = trim((string) ($course['title'] ?? 'Imported course'));
    if ($title === '') $title = 'Imported course';
    $slug = ld_unique_slug(slugify_title($title));
    $coursesDir = rtrim(lms_config()['courses_dir'], '/');
    $outDir = $coursesDir . '/' . $slug;
    $mediaDir = $outDir . '/media';
    if (!@mkdir($outDir, 0775, true) && !is_dir($outDir)) {
        return [false, 'Could not create the course folder. Check filesystem permissions.', []];
    }

    // Module titles/order from the export's course_sections; fall back to prefixes.
    [$moduleOrder, $moduleMeta] = ld_derive_modules((string) ($course['course_sections'] ?? ''));

    // Index topics by parent lesson title.
    $topicsByLesson = [];
    foreach ($topicsRaw as $t) {
        $topicsByLesson[$t['lesson'] ?? ''][] = $t;
    }

    $buckets = array_fill_keys($moduleOrder, []);
    $totalVideos = 0;

    foreach ($lessonsRaw as $i => $lesson) {
        $ltitle = trim((string) ($lesson['title'] ?? 'Lesson'));
        $content = ld_clean_content((string) ($lesson['content'] ?? ''));
        $videos = ld_extract_videos((string) ($lesson['content'] ?? ''));
        $totalVideos += count($videos);

        $topics = [];
        foreach ($topicsByLesson[$lesson['title'] ?? ''] ?? [] as $t) {
            $tv = ld_extract_videos((string) ($t['content'] ?? ''));
            $totalVideos += count($tv);
            $topics[] = [
                'id' => 'topic-' . ($t['topic_id'] ?? uniqid()),
                'title' => trim((string) ($t['title'] ?? 'Topic')),
                'slug' => slugify_title((string) ($t['title'] ?? 'topic')),
                'content' => ld_clean_content((string) ($t['content'] ?? '')),
                'videos' => $tv,
            ];
        }

        $key = ld_module_key_for($ltitle);
        if (!array_key_exists($key, $buckets)) $key = $moduleOrder[array_key_last($moduleOrder)] ?? '0';
        $buckets[$key][] = [
            'id' => 'lesson-' . ($lesson['lesson_id'] ?? $i),
            'title' => $ltitle,
            'slug' => slugify_title($ltitle),
            'content' => $content,
            'videos' => $videos,
            'topics' => $topics,
            '_order' => ld_sort_index($ltitle),
            '_orig' => $i,
        ];
    }

    $modules = [];
    foreach ($moduleOrder as $key) {
        $items = $buckets[$key];
        if (!$items) continue;
        usort($items, fn($a, $b) => ($a['_order'] <=> $b['_order']) ?: ($a['_orig'] <=> $b['_orig']));
        foreach ($items as &$l) { unset($l['_order'], $l['_orig']); }
        unset($l);
        $meta = $moduleMeta[$key];
        $modules[] = [
            'id' => 'module-' . $key,
            'key' => $key,
            'label' => $meta['label'],
            'title' => $meta['title'],
            'summary' => $meta['summary'],
            'lessons' => $items,
        ];
    }

    $lessonCount = array_sum(array_map(fn($m) => count($m['lessons']), $modules));
    $topicCount = 0;
    foreach ($modules as $m) foreach ($m['lessons'] as $l) $topicCount += count($l['topics']);

    $provider = function_exists('setting') ? (setting('org_name', '') ?: 'Sapiqo') : 'Sapiqo';
    $courseJson = [
        'slug' => $slug,
        'title' => $title,
        'provider' => $provider,
        'tagline' => '',
        'tags' => array_values(array_filter(array_map('trim', explode(',', (string) ($course['tag'] ?? ''))))),
        'overview_html' => ld_clean_content((string) ($course['content'] ?? '')),
        'source_course_id' => $course['course_id'] ?? null,
        'stats' => [
            'modules' => count($modules),
            'core_modules' => count(array_filter($modules, fn($m) => ctype_digit((string) $m['key']) && $m['key'] !== '0')),
            'lessons' => $lessonCount,
            'topics' => $topicCount,
            'videos' => $totalVideos,
        ],
        'modules' => $modules,
    ];

    $imagesMirrored = 0; $imagesFailed = 0;
    if ($mirrorImages) {
        [$imagesMirrored, $imagesFailed] = ld_mirror_images($courseJson, $mediaDir);
    }

    file_put_contents($outDir . '/course.json',
        json_encode($courseJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    file_put_contents($outDir . '/source-export.json', $raw);
    ld_write_reader_shell($outDir, $title, $provider);

    return [true, $slug, [
        'title' => $title, 'slug' => $slug,
        'modules' => count($modules), 'lessons' => $lessonCount,
        'topics' => $topicCount, 'videos' => $totalVideos,
        'images_mirrored' => $imagesMirrored, 'images_failed' => $imagesFailed,
    ]];
}

// --- HTML cleaning (mirrors build.py) ----------------------------------------

function ld_clean_content(string $raw): string {
    // Replace Vimeo embed figures with responsive iframes (keeps them playable).
    $text = preg_replace_callback(
        '~<figure[^>]*wp-block-embed[^>]*>.*?(?:https?://)?vimeo\.com/(?:video/)?(\d+).*?</figure>~is',
        fn($m) => ld_responsive_vimeo($m[1]),
        $raw
    ) ?? $raw;
    // Strip all WordPress Gutenberg block comments.
    $text = preg_replace('~<!--\s*/?wp:[^>]*?-->~s', '', $text) ?? $text;
    // Remove empty paragraphs left behind.
    $text = preg_replace('~<p[^>]*>(?:\s|&nbsp;|\x{00a0})*</p>~iu', '', $text) ?? $text;
    // Collapse runs of blank lines.
    $text = preg_replace('~\n{3,}~', "\n\n", $text) ?? $text;
    return editor_sanitize_html($text);
}

function ld_responsive_vimeo(string $videoId): string {
    $src = 'https://player.vimeo.com/video/' . $videoId . '?dnt=1';
    return '<div class="video-embed"><iframe src="' . e($src) . '" '
         . 'title="Course video (Vimeo ' . e($videoId) . ')" loading="lazy" frameborder="0" '
         . 'allow="autoplay; fullscreen; picture-in-picture" allowfullscreen '
         . 'referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
}

function ld_extract_videos(string $content): array {
    preg_match_all('~vimeo\.com/(?:video/)?(\d+)~', $content, $m);
    $seen = [];
    foreach ($m[1] as $id) if (!in_array($id, $seen, true)) $seen[] = $id;
    return array_map(fn($id) => ['provider' => 'vimeo', 'id' => $id, 'url' => 'https://vimeo.com/' . $id], $seen);
}

// --- Module derivation (mirrors build.py) ------------------------------------

function ld_module_key_for(string $title): string {
    $title = trim($title);
    if (preg_match('~^(\d+)\.(\d+)~', $title, $m)) return $m[1];
    $low = strtolower($title);
    if (str_contains($low, 'badge') || str_contains($low, 'certificate')) return 'badge';
    if (preg_match('~\bmodule\s+(\d+)~', $low, $m2)) return $m2[1];
    return '0';
}

function ld_sort_index(string $title): array {
    if (preg_match('~^(\d+)\.(\d+)~', trim($title), $m)) return [0, (int) $m[1], (int) $m[2]];
    return [1, 0, 0];
}

function ld_derive_modules(string $sectionsJson): array {
    $sections = json_decode($sectionsJson ?: '[]', true);
    if (!is_array($sections) || !$sections) {
        // Fallback: generic module buckets.
        $order = ['0', '1', '2', '3', '4', '5', 'badge'];
        $meta = [];
        foreach ($order as $k) {
            $meta[$k] = $k === '0' ? ['title' => 'Welcome', 'label' => 'Start Here', 'summary' => '']
                      : ($k === 'badge' ? ['title' => 'Finish', 'label' => 'Finish', 'summary' => '']
                      : ['title' => 'Module ' . $k, 'label' => 'Module ' . $k, 'summary' => '']);
        }
        return [$order, $meta];
    }
    usort($sections, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    $order = []; $meta = [];
    foreach ($sections as $s) {
        $title = trim((string) ($s['post_title'] ?? ''));
        $low = strtolower($title);
        if (str_contains($low, 'welcome') || (int) ($s['order'] ?? 0) === 0) { $key = '0'; $label = 'Start Here'; }
        elseif (str_contains($low, 'badge') || str_contains($low, 'certificate')) { $key = 'badge'; $label = 'Finish'; }
        elseif (preg_match('~(?:module\s+)?(\d+)\s*[:.]~i', $title, $m)) { $key = $m[1]; $label = 'Module ' . $m[1]; }
        else { $key = slugify_title($title); $label = $title; }
        if (!isset($meta[$key])) { $order[] = $key; $meta[$key] = ['title' => $title ?: $label, 'label' => $label, 'summary' => '']; }
    }
    // Ensure a 'badge' bucket exists so a "Get Your Badge" lesson has a home.
    if (!isset($meta['badge'])) { $order[] = 'badge'; $meta['badge'] = ['title' => 'Finish', 'label' => 'Finish', 'summary' => '']; }
    return [$order, $meta];
}

// --- Image mirroring (SSRF-guarded) ------------------------------------------

function ld_mirror_images(array &$courseJson, string $mediaDir): array {
    if (!@mkdir($mediaDir, 0775, true) && !is_dir($mediaDir)) return [0, 0];
    $map = [];   // url => local name
    $ok = 0; $fail = 0; $cap = 500;

    $rewrite = function (string $html) use (&$map, &$cap): string {
        return preg_replace_callback('~(<img\b[^>]*?\bsrc=")([^"]+)(")~i', function ($m) use (&$map, &$cap) {
            $url = $m[2];
            if (!str_starts_with($url, 'http')) return $m[0];
            if (!isset($map[$url])) {
                if ($cap <= 0) return $m[0];
                $base = preg_replace('~[?#].*$~', '', $url);
                $base = substr(strrchr('/' . $base, '/'), 1) ?: 'image';
                $base = preg_replace('~[^A-Za-z0-9._-]~', '_', $base);
                $map[$url] = substr(sha1($url), 0, 8) . '-' . $base;
            }
            return $m[1] . 'media/' . $map[$url] . $m[3];
        }, $html) ?? $html;
    };

    $courseJson['overview_html'] = $rewrite($courseJson['overview_html'] ?? '');
    foreach ($courseJson['modules'] as &$mod) {
        foreach ($mod['lessons'] as &$l) {
            $l['content'] = $rewrite($l['content']);
            foreach ($l['topics'] as &$t) { $t['content'] = $rewrite($t['content']); }
            unset($t);
        }
        unset($l);
    }
    unset($mod);

    foreach ($map as $url => $name) {
        $dest = $mediaDir . '/' . $name;
        if (is_file($dest)) { $ok++; continue; }
        $body = function_exists('safe_fetch') ? safe_fetch($url, 15, 25_000_000) : null;
        if ($body === null || $body === '') { $fail++; continue; }
        file_put_contents($dest, $body);
        $ok++;
    }
    return [$ok, $fail];
}

// --- Helpers -----------------------------------------------------------------

function slugify_title(string $title): string {
    $s = strtolower($title);
    $s = preg_replace('~[^\w\s.-]~u', '', $s) ?? $s;
    $s = preg_replace('~[\s.]+~', '-', $s) ?? $s;
    $s = preg_replace('~-+~', '-', $s) ?? $s;
    $s = trim($s, '-');
    return $s !== '' ? $s : 'course';
}

function ld_unique_slug(string $slug): string {
    $base = $slug; $n = 2;
    $coursesDir = rtrim(lms_config()['courses_dir'], '/');
    while (is_dir($coursesDir . '/' . $slug) || (function_exists('course_by_slug') && course_by_slug($slug))) {
        $slug = $base . '-' . $n; $n++;
    }
    return $slug;
}

// Write the shared reader shell (same as the other courses' index.html).
function ld_write_reader_shell(string $outDir, string $title, string $provider): void {
    $t = e($title); $p = e($provider);
    $mark = e(mb_substr($provider, 0, 1) ?: 'S');
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{$t} · {$p} Courses</title>
  <meta name="description" content="{$t}" />
  <link rel="stylesheet" href="../assets/css/fonts.css" />
  <link rel="stylesheet" href="../assets/css/theme.css" />
  <link rel="stylesheet" href="../assets/css/reader.css" />
</head>
<body>
  <a class="skip-link" href="#reader">Skip to lesson content</a>
  <header class="topbar">
    <a class="brand" href="../index.html"><span class="brand__mark">{$mark}</span><span>{$p} Courses</span></a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle course navigation">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      Contents
    </button>
  </header>
  <div class="app" id="app" data-nav-open="false">
    <aside class="sidebar" aria-label="Course navigation">
      <div class="sidebar__head">
        <a class="brand" href="../index.html"><span class="brand__mark">{$mark}</span><span>{$p} Courses</span></a>
        <h1 class="sidebar__course-title" id="courseTitle">Loading…</h1>
        <div class="sidebar__progress-meta"><span id="sideProgressCount">0 / 0 complete</span><strong id="sideProgressPct">0%</strong></div>
        <div class="progress progress--on-navy"><div class="progress__bar" id="sideProgressBar"></div></div>
      </div>
      <nav class="sidebar__nav" id="nav" aria-label="Modules and lessons"></nav>
    </aside>
    <main class="reader" id="reader" tabindex="-1"></main>
    <div class="backdrop" id="backdrop"></div>
  </div>
  <script src="../assets/js/reader.js"></script>
</body>
</html>
HTML;
    file_put_contents($outDir . '/index.html', $html);
}
