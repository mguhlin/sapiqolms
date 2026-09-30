<?php
// PHP-native Markdown -> course builder, powering the in-browser course editor
// (Admin -> Create a course). Mirrors creator/build_course.py so districts can
// author new courses with no Python. Handles text lessons, Vimeo videos, URL
// images (mirrored locally), and resources. Local video files + Whisper captions
// remain a CLI step (creator/build_course.py --transcribe).

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// --- Markdown -> HTML (mirrors the Python renderer) --------------------------

function cr_inline(string $t): string {
    $t = htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $t = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) {
        $u = safe_media_ref(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
        return $u === '' ? $m[1] : '<img src="' . e($u) . '" alt="' . $m[1] . '">';
    }, $t);
    $t = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
        $u = safe_media_ref(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
        return $u === '' ? $m[1] : '<a href="' . e($u) . '">' . $m[1] . '</a>';
    }, $t);
    $t = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t);
    $t = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $t);
    $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t);
    return $t;
}

function cr_md_to_html(string $md): string {
    $lines = explode("\n", $md);
    $n = count($lines);
    $out = []; $para = [];
    $flush = function () use (&$out, &$para) {
        if ($para) { $out[] = '<p>' . cr_inline(trim(implode(' ', $para))) . '</p>'; $para = []; }
    };
    for ($i = 0; $i < $n; ) {
        $s = trim($lines[$i]);
        if ($s === '') { $flush(); $i++; continue; }
        if (preg_match('/^(---|\*\*\*|___)$/', $s)) { $flush(); $out[] = '<hr>'; $i++; continue; }
        if (preg_match('/^(#{3,6})\s+(.*)$/', $s, $m)) {
            $flush(); $lvl = strlen($m[1]);
            $out[] = "<h$lvl>" . cr_inline(trim($m[2])) . "</h$lvl>"; $i++; continue;
        }
        if (preg_match('/^>\s?/', $s)) {
            $flush(); $buf = [];
            while ($i < $n && preg_match('/^>\s?/', trim($lines[$i]))) {
                $buf[] = preg_replace('/^>\s?/', '', trim($lines[$i])); $i++;
            }
            $out[] = '<blockquote>' . cr_md_to_html(implode("\n", $buf)) . '</blockquote>'; continue;
        }
        if (preg_match('/^[-*]\s+/', $s)) {
            $flush(); $items = [];
            while ($i < $n && preg_match('/^[-*]\s+/', trim($lines[$i]))) {
                $items[] = preg_replace('/^[-*]\s+/', '', trim($lines[$i])); $i++;
            }
            $out[] = '<ul>' . implode('', array_map(fn($x) => '<li>' . cr_inline($x) . '</li>', $items)) . '</ul>'; continue;
        }
        if (preg_match('/^\d+\.\s+/', $s)) {
            $flush(); $items = [];
            while ($i < $n && preg_match('/^\d+\.\s+/', trim($lines[$i]))) {
                $items[] = preg_replace('/^\d+\.\s+/', '', trim($lines[$i])); $i++;
            }
            $out[] = '<ol>' . implode('', array_map(fn($x) => '<li>' . cr_inline($x) . '</li>', $items)) . '</ol>'; continue;
        }
        if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)$/', $s, $m)) {
            $flush();
            $out[] = '<figure class="course-image"><img src="' . e(safe_media_ref($m[2])) . '" alt="'
                   . htmlspecialchars($m[1]) . '"></figure>'; $i++; continue;
        }
        $para[] = $s; $i++;
    }
    $flush();
    return implode("\n", $out);
}

// --- Helpers -----------------------------------------------------------------

function cr_slugify(string $t): string {
    $s = strtolower(preg_replace('/[^\w\s-]/', '', $t));
    $s = preg_replace('/[\s_]+/', '-', $s);
    return trim(preg_replace('/-+/', '-', $s), '-') ?: 'course';
}

function cr_module_meta(string $title): array {
    $low = strtolower($title);
    if (str_contains($low, 'welcome') || str_starts_with($low, 'start')) return ['0', 'Start Here'];
    if (str_contains($low, 'badge') || str_contains($low, 'certificate')) return ['badge', 'Finish'];
    if (preg_match('/(?:module\s+)?(\d+)/', $title, $m) && preg_match('/^\s*(module\s+)?\d+\b/i', $title))
        return [$m[1], 'Module ' . $m[1]];
    return [cr_slugify($title), $title];
}

function cr_video_html(array $v): string {
    if ($v['provider'] === 'vimeo') {
        return '<div class="video-embed"><iframe src="https://player.vimeo.com/video/' . $v['id']
            . '?dnt=1" title="Course video" loading="lazy" frameborder="0" '
            . 'allow="autoplay; fullscreen; picture-in-picture" allowfullscreen '
            . 'referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
    }
    if ($v['provider'] === 'youtube') {
        return '<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/' . $v['id']
            . '" title="Course video" loading="lazy" frameborder="0" '
            . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
            . 'allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
    }
    return '<div class="video-embed"><video controls preload="metadata" playsinline title="Course video">'
        . '<source src="' . htmlspecialchars($v['file']) . '" type="video/mp4" />'
        . 'Your browser cannot play this video.</video></div>';
}

// Detect the video provider from a URL. Returns a video spec or null.
function cr_video_spec(string $arg): ?array {
    if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#', $arg, $m)) {
        return ['provider' => 'youtube', 'id' => $m[1], 'url' => 'https://youtu.be/' . $m[1]];
    }
    if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $arg, $m)) {
        return ['provider' => 'vimeo', 'id' => $m[1], 'url' => 'https://vimeo.com/' . $m[1]];
    }
    return null;
}

// General-purpose embed for @embed: video providers, Google Docs/Slides/Drive,
// direct video URLs, else a plain link. Course developers (not just full admins)
// can author this, so the fallback below must not trust the URL's scheme.
function cr_embed_html(string $url): string {
    $u = trim($url);
    $spec = cr_video_spec($u);
    if ($spec) return cr_video_html($spec);
    if (preg_match('#https?://(?:docs|drive)\.google\.com/\S+#', $u)) {
        $src = preg_replace('#/(edit|view|pub)(\?[^"]*)?$#', '/embed', $u);
        return '<div class="video-embed"><iframe src="' . htmlspecialchars($src)
            . '" title="Embedded document" loading="lazy" frameborder="0" allowfullscreen></iframe></div>';
    }
    if (preg_match('#^https?://.*\.(mp4|webm|m4v)(\?\S*)?$#i', $u)) {
        return '<div class="video-embed"><video controls preload="metadata" playsinline>'
            . '<source src="' . htmlspecialchars($u) . '" /></video></div>';
    }
    // Fallback plain link — only ever emit it for an http(s) URL. Without this
    // check, an unrecognized scheme like "javascript:…" would be echoed straight
    // into href="" (fully quoted, so the sanitizer-style quote-anchored regexes
    // used elsewhere wouldn't even apply — there's no separate sanitizer here).
    $safe = function_exists('safe_url') ? safe_url($u) : (preg_match('#^https?://#i', $u) ? $u : '');
    if ($safe === '') return '<p>' . htmlspecialchars($u) . '</p>';
    return '<p><a href="' . htmlspecialchars($safe) . '" target="_blank" rel="noopener">' . htmlspecialchars($safe) . '</a></p>';
}

// Parse the body of an @quiz ... @endquiz block into a quiz structure. Supports:
//   pass: 70          percent needed to pass (default 70)
//   shuffle: true     randomize option order per attempt
//   pick: 5           ask a random N-question subset (question bank)
//   attempts: 3       max attempts (0/unset = unlimited)
//   Q: text (multiple)  multiple-choice; options as "- opt" / "- *correct"
//   TF: text          true/false; mark with "= true" or "= false"
//   SA: text          short answer; accepted answers as "= answer" lines
//   feedback: text    explanation shown after answering (per question)
function cr_parse_quiz(array $lines): array {
    $pass = 70; $shuffle = false; $pick = 0; $attempts = 0;
    $questions = []; $cur = null;
    $flush = function () use (&$cur, &$questions) {
        if ($cur === null) return;
        if (($cur['type'] ?? '') !== 'short') {
            $nc = count(array_filter($cur['options'], fn($o) => $o['correct']));
            if ($nc > 1) $cur['type'] = 'multiple';
            elseif (($cur['type'] ?? '') !== 'multiple') $cur['type'] = 'single';
        }
        $questions[] = $cur; $cur = null;
    };
    foreach ($lines as $raw) {
        $t = trim($raw);
        if ($t === '') continue;
        if (preg_match('/^pass:\s*(\d+)/i', $t, $m))     { $pass = max(1, min(100, (int) $m[1])); continue; }
        if (preg_match('/^shuffle:\s*(true|yes|1)/i', $t)) { $shuffle = true; continue; }
        if (preg_match('/^pick:\s*(\d+)/i', $t, $m))     { $pick = max(0, (int) $m[1]); continue; }
        if (preg_match('/^attempts:\s*(\d+)/i', $t, $m)) { $attempts = max(0, (int) $m[1]); continue; }
        if (preg_match('/^feedback:\s*(.*)$/i', $t, $m)) { if ($cur) $cur['feedback'] = trim($m[1]); continue; }
        if (preg_match('/^Q[:.]\s*(.*)$/i', $t, $m)) {
            $flush(); $text = $m[1]; $multiple = false;
            if (preg_match('/\(multiple\)\s*$/i', $text)) { $multiple = true; $text = trim(preg_replace('/\(multiple\)\s*$/i', '', $text)); }
            $cur = ['text' => $text, 'type' => $multiple ? 'multiple' : 'single', 'options' => [], 'feedback' => ''];
            continue;
        }
        if (preg_match('/^TF[:.]\s*(.*)$/i', $t, $m)) {
            $flush();
            $cur = ['text' => trim($m[1]), 'type' => 'single', 'feedback' => '',
                    'options' => [['text' => 'True', 'correct' => false], ['text' => 'False', 'correct' => false]]];
            continue;
        }
        if (preg_match('/^SA[:.]\s*(.*)$/i', $t, $m)) {
            $flush();
            $cur = ['text' => trim($m[1]), 'type' => 'short', 'answers' => [], 'feedback' => ''];
            continue;
        }
        // "= value": accepted short answer, or TF correctness.
        if ($cur && preg_match('/^=\s*(.*)$/', $t, $m)) {
            $val = trim($m[1]);
            if (($cur['type'] ?? '') === 'short') { if ($val !== '') $cur['answers'][] = $val; }
            else {  // TF / choice: mark the matching option correct
                foreach ($cur['options'] as &$o) {
                    if (strcasecmp($o['text'], $val) === 0) $o['correct'] = true;
                }
                unset($o);
            }
            continue;
        }
        if ($cur && ($cur['type'] ?? '') !== 'short' && preg_match('/^[-*+]\s+(.*)$/', $t, $m)) {
            $opt = trim($m[1]); $correct = false;
            if (str_starts_with($opt, '*')) { $correct = true; $opt = trim(substr($opt, 1)); }
            if ($opt !== '') $cur['options'][] = ['text' => $opt, 'correct' => $correct];
        }
    }
    $flush();
    // Keep valid questions: choice needs >=1 correct option; short needs >=1 answer.
    $questions = array_values(array_filter($questions, function ($q) {
        if ($q['text'] === '') return false;
        if (($q['type'] ?? '') === 'short') return !empty($q['answers']);
        return count(array_filter($q['options'] ?? [], fn($o) => $o['correct'])) > 0;
    }));
    // Add a stable id per question so pick/shuffle can be graded statelessly.
    foreach ($questions as $i => &$q) { $q['qid'] = 'q' . $i; }
    unset($q);
    $out = ['pass' => $pass, 'questions' => $questions];
    if ($shuffle) $out['shuffle'] = true;
    if ($pick > 0) $out['pick'] = $pick;
    if ($attempts > 0) $out['attempts'] = $attempts;
    return $out;
}

// Copy a staged editor upload (upload:<name>) into the course media dir.
// Returns the relative media path, or '' if the staged file is missing.
function cr_copy_upload(string $name, string $destDir): string {
    $name = basename($name);
    if (!preg_match('/^[a-z0-9._-]+$/i', $name)) return '';
    $src = rtrim(lms_config()['upload_dir'], '/') . '/' . $name;
    if (!is_file($src)) return '';
    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
    @copy($src, $destDir . '/' . $name);
    return $name;
}

function cr_mirror_image(string $url, string $mediaDir): string {
    if (str_starts_with($url, 'upload:')) {
        $name = cr_copy_upload(substr($url, 7), $mediaDir);
        return $name !== '' ? 'media/' . $name : $url;
    }
    if (!preg_match('#^https?://#', $url)) return $url;   // leave non-URLs as-is
    $base = preg_replace('/[?#].*$/', '', $url);
    $base = substr($base, (int) strrpos($base, '/') + 1) ?: 'image';
    $name = substr(sha1($url), 0, 8) . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
    $dest = $mediaDir . '/' . $name;
    if (!is_file($dest)) {
        // SSRF-safe fetch (blocks internal/metadata hosts). Leaves the original
        // URL in place if the fetch is disallowed or fails.
        $data = function_exists('safe_fetch') ? safe_fetch($url) : null;
        if ($data === null) return $url;
        @file_put_contents($dest, $data);
    }
    return 'media/' . $name;
}

// Load a course's saved Markdown source (from <slug>/source.md) for editing.
function creator_load_source(string $slug): ?string {
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) return null;
    $path = rtrim(lms_config()['courses_dir'], '/') . '/' . $slug . '/source.md';
    return is_file($path) ? (string) file_get_contents($path) : null;
}

// Hide/show a course in the catalog without deleting it (a .disabled marker
// file the scanner respects). Returns true on success.
function creator_set_disabled(string $slug, bool $disabled): bool {
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) return false;
    $dir = rtrim(lms_config()['courses_dir'], '/') . '/' . $slug;
    if (!is_dir($dir)) return false;
    $marker = $dir . '/.disabled';
    if ($disabled) { @file_put_contents($marker, gmdate('c') . "\n"); }
    else { if (is_file($marker)) @unlink($marker); }
    return true;
}

// Read the title/slug from a Markdown document's front matter (for drafts).
function creator_front_matter(string $text): array {
    $meta = [];
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n?/s', $text, $m)) {
        foreach (explode("\n", $m[1]) as $line) {
            if (str_contains($line, ':')) { [$k, $v] = explode(':', $line, 2); $meta[strtolower(trim($k))] = trim($v); }
        }
    }
    return $meta;
}

// Turn a course's source into a clone: give it a fresh slug + title so publishing
// creates a new course instead of overwriting the original.
function creator_clone_source(string $slug): ?string {
    $src = creator_load_source($slug);
    if ($src === null) return null;
    $coursesRoot = rtrim(lms_config()['courses_dir'], '/');
    $newSlug = $slug . '-copy'; $n = 2;
    while (is_dir($coursesRoot . '/' . $newSlug)) { $newSlug = $slug . '-copy' . $n; $n++; }
    $src = preg_replace('/^(\s*slug:\s*).*$/mi', '${1}' . $newSlug, $src, 1);
    $src = preg_replace('/^(\s*title:\s*)(.*)$/mi', '${1}${2} (copy)', $src, 1);
    return $src;
}

// --- Editor drafts (per-admin, unpublished) ---------------------------------

function draft_save(int $adminId, string $markdown): int {
    $meta = creator_front_matter($markdown);
    $title = $meta['title'] ?? 'Untitled draft';
    $slug = $meta['slug'] ?? cr_slugify($title);
    $existing = db_one('SELECT id FROM course_drafts WHERE admin_id=? AND slug=?', [$adminId, $slug]);
    if ($existing) {
        db_run('UPDATE course_drafts SET title=?, markdown=?, updated_at=? WHERE id=?',
            [$title, $markdown, now_utc(), $existing['id']]);
        return (int) $existing['id'];
    }
    return db_insert('INSERT INTO course_drafts (admin_id,slug,title,markdown,updated_at) VALUES (?,?,?,?,?)',
        [$adminId, $slug, $title, $markdown, now_utc()]);
}

function drafts_for(int $adminId): array {
    return db_all('SELECT * FROM course_drafts WHERE admin_id=? ORDER BY updated_at DESC', [$adminId]);
}

function draft_get(int $adminId, int $id): ?array {
    return db_one('SELECT * FROM course_drafts WHERE id=? AND admin_id=?', [$id, $adminId]);
}

function draft_delete(int $adminId, int $id): void {
    db_run('DELETE FROM course_drafts WHERE id=? AND admin_id=?', [$id, $adminId]);
}

// Starter Markdown shown in the editor.
function creator_sample(): string {
    return <<<MD
---
title: My New Course
slug: my-new-course
tagline: One sentence shown on the catalog card and course home.
tags: Topic, Audience
about: A short paragraph introducing the course, shown on the course home page.
---

# Module 1: Getting Started

## 1.1 Welcome

Write normal Markdown here: **bold**, *italic*, `code`, and
[links](https://example.org). Blank lines separate paragraphs.

- Bulleted lists work
- So do numbered lists

### A sub-heading inside the lesson

> Blockquotes are supported too.

@video https://vimeo.com/76979871

@resource [A helpful guide](https://example.org) Optional note after the link

## 1.2 A Second Lesson

More content for the second lesson…

@quiz
pass: 70
Q: Which heading starts a new module?
- ## Lesson
- *# Module
- ### Sub-heading
Q: Which are valid directives? (multiple)
- *@video
- *@resource
- @paragraph
@endquiz

# Module 2: Going Deeper

## 2.1 Another Lesson

Keep adding modules with `#` and lessons with `##`.

# Get Your Certificate

## Finish and claim your badge

When you complete every step, your certificate and badge appear on your dashboard.
MD;
}

// --- Build a course from Markdown -> returns [ok, slugOrError] ----------------

function create_course_from_markdown(string $text): array {
    // Front matter
    $meta = []; $body = $text;
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $text, $m)) {
        foreach (explode("\n", $m[1]) as $line) {
            if (str_contains($line, ':')) { [$k, $v] = explode(':', $line, 2); $meta[strtolower(trim($k))] = trim($v); }
        }
        $body = $m[2];
    }
    $title = $meta['title'] ?? '';
    if ($title === '') return [false, 'Add a "title:" line to the front matter.'];
    $slug = $meta['slug'] ?? cr_slugify($title);
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) $slug = cr_slugify($slug);
    if (in_array($slug, ['sapiqo', 'sapiqo-data', 'assets', 'creator'], true))
        return [false, 'That slug is reserved. Choose another.'];

    $coursesRoot = rtrim(lms_config()['courses_dir'], '/');
    $outDir = $coursesRoot . '/' . $slug;
    $mediaDir = $outDir . '/media';
    @mkdir($mediaDir . '/videos', 0775, true);

    $modules = []; $curMod = null; $curLesson = null;
    $bodyBuf = []; $videos = []; $videoHtml = []; $resources = [];
    $inQuiz = false; $quizLines = [];

    $flush = function () use (&$bodyBuf, &$videos, &$videoHtml, &$resources, $mediaDir) {
        $block = implode("\n", $bodyBuf);
        $block = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/',
            fn($mm) => '![' . $mm[1] . '](' . cr_mirror_image($mm[2], $mediaDir) . ')', $block);
        $html = cr_md_to_html($block);
        foreach ($videoHtml as $k => $vh) {
            $html = str_replace('<p>@@VIDEO' . $k . '@@</p>', $vh, $html);
            $html = str_replace('@@VIDEO' . $k . '@@', $vh, $html);
        }
        if ($resources) $html .= "\n<h3>Resources</h3><ul class=\"resources\">" . implode('', $resources) . '</ul>';
        $vids = $videos;
        $bodyBuf = []; $videos = []; $videoHtml = []; $resources = [];
        return [$html, $vids];
    };
    $closeLesson = function () use (&$curLesson, &$curMod, &$modules, $flush) {
        if ($curLesson !== null) {
            [$html, $vids] = $flush();
            $curLesson['content'] = $html; $curLesson['videos'] = $vids;
            $curMod['lessons'][] = $curLesson; $curLesson = null;
        }
    };

    foreach (explode("\n", $body) as $line) {
        $s = trim($line);
        if ($inQuiz) {
            if (preg_match('/^@endquiz\b/i', $s)) {
                if ($curLesson !== null) $curLesson['quiz'] = cr_parse_quiz($quizLines);
                $inQuiz = false; $quizLines = [];
            } else {
                $quizLines[] = $line;
            }
            continue;
        }
        if (preg_match('/^@quiz\b/i', $s)) { $inQuiz = true; $quizLines = []; continue; }
        if (preg_match('/^#\s+(.*)$/', $s, $m)) {
            $closeLesson();
            if ($curMod !== null) $modules[] = $curMod;
            [$key, $label] = cr_module_meta(trim($m[1]));
            $curMod = ['key' => $key, 'label' => $label, 'title' => trim($m[1]), 'lessons' => []];
            continue;
        }
        if (preg_match('/^##\s+(.*)$/', $s, $m)) {
            $closeLesson();
            if ($curMod === null) $curMod = ['key' => '0', 'label' => 'Start Here', 'title' => 'Welcome', 'lessons' => []];
            $curLesson = ['title' => trim($m[1]), 'topics' => []];
            continue;
        }
        if (preg_match('/^@(video|resource|image|embed)\s+(.*)$/', $s, $m)) {
            $kind = $m[1]; $arg = trim($m[2]);
            if ($kind === 'video') {
                $spec = cr_video_spec($arg);
                if ($spec) {
                    $v = $spec;
                } elseif (str_starts_with($arg, 'upload:')) {
                    $name = cr_copy_upload(substr($arg, 7), $mediaDir . '/videos');
                    if ($name === '') { $bodyBuf[] = '*(Uploaded video not found.)*'; continue; }
                    $v = ['provider' => 'local', 'file' => 'media/videos/' . $name];
                } elseif (preg_match('#^https?://.*\.(mp4|webm|m4v)$#i', $arg)) {
                    $v = ['provider' => 'local', 'file' => $arg];
                } else {
                    $bodyBuf[] = '*(Video "' . htmlspecialchars($arg) . '" — add local files with the command-line builder.)*';
                    continue;
                }
                $videos[] = $v;
                $bodyBuf[] = "\n@@VIDEO" . count($videoHtml) . "@@\n";
                $videoHtml[] = cr_video_html($v);
            } elseif ($kind === 'embed') {
                $bodyBuf[] = "\n@@VIDEO" . count($videoHtml) . "@@\n";
                $videoHtml[] = cr_embed_html($arg);
            } elseif ($kind === 'resource') {
                if (preg_match('/\[([^\]]+)\]\(([^)]+)\)\s*(.*)$/', $arg, $lm)) {
                    $extra = $lm[3] !== '' ? ' — ' . htmlspecialchars($lm[3]) : '';
                    $resources[] = '<li><a href="' . e(safe_media_ref($lm[2])) . '"><strong>'
                        . htmlspecialchars($lm[1]) . '</strong></a>' . $extra . '</li>';
                }
            } elseif ($kind === 'image') {
                if (preg_match('/(\S+)(?:\s+"([^"]*)")?/', $arg, $im))
                    $bodyBuf[] = '![' . ($im[2] ?? '') . '](' . $im[1] . ')';
            }
            continue;
        }
        $bodyBuf[] = $line;
    }
    $closeLesson();
    if ($curMod !== null) $modules[] = $curMod;
    if (!$modules) return [false, 'Add at least one "# Module" and "## Lesson".'];

    $totalVideos = 0; $totalQuizzes = 0; $outModules = [];
    foreach ($modules as $mi => $mod) {
        $lessons = [];
        foreach ($mod['lessons'] as $li => $les) {
            $totalVideos += count($les['videos'] ?? []);
            $lesson = [
                'id' => "lesson-$mi-$li", 'title' => $les['title'], 'slug' => cr_slugify($les['title']),
                'content' => $les['content'] ?? '', 'videos' => $les['videos'] ?? [], 'topics' => [],
            ];
            if (!empty($les['quiz']['questions'])) {
                $quiz = $les['quiz'];
                $quiz['id'] = "quiz-$mi-$li";
                $lesson['quiz'] = $quiz;
                $totalQuizzes++;
            }
            $lessons[] = $lesson;
        }
        $outModules[] = ['id' => 'module-' . $mod['key'], 'key' => $mod['key'], 'label' => $mod['label'],
            'title' => $mod['title'], 'summary' => '', 'lessons' => $lessons];
    }
    $lessonCount = array_sum(array_map(fn($m) => count($m['lessons']), $outModules));

    $course = [
        'slug' => $slug, 'title' => $title, 'provider' => lms_config()['org_name'] ?? '',
        'tagline' => $meta['tagline'] ?? '',
        'tags' => array_values(array_filter(array_map('trim', explode(',', $meta['tags'] ?? '')))),
        'overview_html' => isset($meta['about']) ? cr_md_to_html($meta['about']) : '',
        'stats' => [
            'modules' => count($outModules),
            'core_modules' => count(array_filter($outModules, fn($m) => str_starts_with($m['label'], 'Module'))),
            'lessons' => $lessonCount, 'topics' => 0, 'videos' => $totalVideos,
            'quizzes' => $totalQuizzes,
        ],
        'modules' => $outModules,
    ];

    file_put_contents($outDir . '/course.json',
        json_encode($course, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    file_put_contents($outDir . '/source.md', $text);
    cr_write_shell($outDir, $title, $meta['tagline'] ?? '');
    if (!empty($meta['badge']) && preg_match('#^https?://#', $meta['badge'])) {
        $img = function_exists('safe_fetch') ? safe_fetch($meta['badge'], 12, 5000000) : null;
        if ($img !== null) @file_put_contents($outDir . '/badge.png', $img);
    }
    return [true, $slug];
}

function cr_write_shell(string $outDir, string $title, string $desc): void {
    file_put_contents($outDir . '/index.html', cr_shell_html($title, $desc));
}

function cr_shell_html(string $title, string $desc, bool $runtime = false): string {
    $t = htmlspecialchars($title, ENT_QUOTES); $d = htmlspecialchars($desc, ENT_QUOTES);
    $home = $runtime ? htmlspecialchars(url('/dashboard'), ENT_QUOTES) : '../index.html';
    $cfg = lms_config();
    $brand = htmlspecialchars($cfg['catalog_name'] ?? ($cfg['app_name'] . ' Courses'), ENT_QUOTES);
    $mark = htmlspecialchars(mb_substr($cfg['brand_mark'] ?? mb_substr($cfg['app_name'], 0, 1), 0, 2), ENT_QUOTES);
    return <<<HTML
<!DOCTYPE html>
<html lang="en"><head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>$t · $brand</title><meta name="description" content="$d" />
  <link rel="stylesheet" href="../assets/css/fonts.css" />
  <link rel="stylesheet" href="../assets/css/theme.css" /><link rel="stylesheet" href="../assets/css/reader.css" />
</head><body>
  <a class="skip-link" href="#reader">Skip to lesson content</a>
  <header class="topbar"><a class="brand" href="$home"><span class="brand__mark">$mark</span><span>$brand</span></a>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle course navigation">Contents</button></header>
  <div class="app" id="app" data-nav-open="false">
    <aside class="sidebar" aria-label="Course navigation"><div class="sidebar__head">
      <a class="brand" href="$home"><span class="brand__mark">$mark</span><span>$brand</span></a>
      <h1 class="sidebar__course-title" id="courseTitle">Loading…</h1>
      <div class="sidebar__progress-meta"><span id="sideProgressCount">0 / 0</span><strong id="sideProgressPct">0%</strong></div>
      <div class="progress progress--on-navy"><div class="progress__bar" id="sideProgressBar"></div></div></div>
      <nav class="sidebar__nav" id="nav" aria-label="Modules and lessons"></nav></aside>
    <main class="reader" id="reader" tabindex="-1"></main><div class="backdrop" id="backdrop"></div>
  </div>
  <script src="../assets/js/reader.js"></script>
</body></html>
HTML;
}
