<?php
// Import an IMS Common Cartridge (.imscc) exported by Canvas, Sakai, Moodle,
// Blackboard, etc. Parses the manifest organization into modules/lessons, pulls
// web-content HTML as lesson content, converts QTI multiple-choice into quizzes,
// and bundles referenced media. Returns an import report listing anything skipped.
//
// This PHP build ships without SimpleXML/DOM, so parsing is done with targeted
// regex extraction — sufficient for the well-structured CC manifest + QTI. If a
// full XML extension is present it would still work unchanged.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/editor.php';
require_once __DIR__ . '/creator.php';     // cr_slugify(), cr_write_shell()
require_once __DIR__ . '/course_io.php';   // io_tmp_dir(), _copy_tree(), _rmtree()

// Returns [ok(bool), slugOrError(string), report(array)].
function import_common_cartridge(string $tmpFile, string $origName): array {
    $report = ['lessons' => 0, 'quizzes' => 0, 'media' => 0, 'missing_media' => 0, 'skipped' => []];
    $work = io_tmp_dir('cc-import-');
    try {
        $staged = $work . '/pkg.zip';   // PharData needs a known archive extension
        copy($tmpFile, $staged);
        if (function_exists('archive_entries_safe') && !archive_entries_safe($staged)) return [false, 'The archive contains unsafe file paths.', $report];
        try { $p = new PharData($staged); $p->extractTo($work, null, true); }
        catch (Throwable $e) { return [false, 'Could not open the .imscc (expected a zip): ' . $e->getMessage(), $report]; }
        @unlink($staged);

        $manifestPath = _cc_find($work, 'imsmanifest.xml');
        if (!$manifestPath) return [false, 'No imsmanifest.xml found in the package.', $report];
        $base = dirname($manifestPath);
        $xml = (string) file_get_contents($manifestPath);

        $title = _cc_first($xml, '#<(?:[a-z]+:)?title[^>]*>\s*(?:<(?:[a-z]+:)?string[^>]*>)?(.*?)(?:</(?:[a-z]+:)?string>)?\s*</(?:[a-z]+:)?title>#is')
            ?: pathinfo($origName, PATHINFO_FILENAME);
        $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8')) ?: 'Imported Course';

        // resource id => [type, href]
        $resources = _cc_resources($xml);
        // organization tree -> modules[] each with lessons[]
        $modules = _cc_organization($xml);

        // If there is no usable organization, fall back to one module of all webcontent.
        if (!$modules) {
            $lessons = [];
            foreach ($resources as $rid => $r) {
                if (str_starts_with($r['type'], 'webcontent') && preg_match('/\.html?$/i', $r['href'])) {
                    $lessons[] = ['title' => pathinfo($r['href'], PATHINFO_FILENAME), 'ref' => $rid];
                }
            }
            $modules = $lessons ? [['title' => 'Course Content', 'lessons' => $lessons]] : [];
        }
        if (!$modules) return [false, 'The cartridge has no importable content (no organization or web content).', $report];

        $slug = _cc_unique_slug(cr_slugify($title));
        $courseDir = rtrim(lms_config()['courses_dir'], '/') . '/' . $slug;
        $mediaDir = $courseDir . '/media';
        @mkdir($mediaDir . '/videos', 0775, true);

        $outModules = [];
        foreach ($modules as $mi => $mod) {
            $lessonsOut = [];
            foreach ($mod['lessons'] as $li => $les) {
                $rid = $les['ref'] ?? '';
                $res = $resources[$rid] ?? null;
                $lesson = ['id' => "lesson-$mi-$li", 'title' => $les['title'] ?: 'Lesson',
                           'slug' => cr_slugify($les['title'] ?: "lesson-$mi-$li"),
                           'content' => '', 'videos' => [], 'topics' => []];
                if ($res && str_starts_with($res['type'], 'webcontent') && preg_match('/\.html?$/i', $res['href'])) {
                    $lesson['content'] = _cc_lesson_content($base, $res['href'], $mediaDir, $report);
                    $report['lessons']++;
                } elseif ($res && str_contains($res['type'], 'imsqti')) {
                    $qtiFile = package_file($base, $res['href']);
                    $quiz = $qtiFile !== null ? _cc_parse_qti((string) file_get_contents($qtiFile)) : null;
                    if ($quiz && $quiz['questions']) {
                        $quiz['id'] = "quiz-$mi-$li";
                        $lesson['quiz'] = $quiz;
                        $lesson['content'] = '<p>Complete the knowledge check below.</p>';
                        $report['quizzes']++;
                    } else {
                        $report['skipped'][] = 'Quiz (unreadable QTI): ' . ($les['title'] ?? $rid);
                        continue;
                    }
                } elseif ($res) {
                    $report['skipped'][] = ($les['title'] ?? $rid) . ' (' . $res['type'] . ')';
                    continue;
                } else {
                    // Item with no resource — treat as a section header lesson.
                    $lesson['content'] = '';
                }
                $lessonsOut[] = $lesson;
            }
            if ($lessonsOut) {
                $outModules[] = ['id' => 'module-' . $mi, 'key' => (string) ($mi + 1),
                    'label' => 'Module ' . ($mi + 1), 'title' => $mod['title'] ?: ('Module ' . ($mi + 1)),
                    'summary' => '', 'lessons' => $lessonsOut];
            }
        }
        if (!$outModules) return [false, 'Nothing importable after parsing (only unsupported item types).', $report];

        $lessonCount = array_sum(array_map(fn($m) => count($m['lessons']), $outModules));
        $quizCount = 0;
        foreach ($outModules as $m) foreach ($m['lessons'] as $l) if (!empty($l['quiz'])) $quizCount++;

        $course = [
            'slug' => $slug, 'title' => $title, 'provider' => 'Imported (Common Cartridge)',
            'tagline' => '', 'tags' => [], 'overview_html' => '',
            'stats' => ['modules' => count($outModules),
                'core_modules' => count($outModules), 'lessons' => $lessonCount,
                'topics' => 0, 'videos' => 0, 'quizzes' => $quizCount],
            'modules' => $outModules,
        ];
        file_put_contents($courseDir . '/course.json',
            json_encode($course, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        cr_write_shell($courseDir, $title, '');
        return [true, $slug, $report];
    } catch (Throwable $e) {
        return [false, 'Import failed: ' . $e->getMessage(), $report];
    } finally {
        _rmtree($work);
    }
}

// --- helpers -----------------------------------------------------------------

function _cc_find(string $dir, string $name): ?string {
    if (is_file("$dir/$name")) return "$dir/$name";
    foreach (glob("$dir/*", GLOB_ONLYDIR) ?: [] as $sub) {
        if (is_file("$sub/$name")) return "$sub/$name";
    }
    return null;
}

function _cc_first(string $xml, string $pattern): ?string {
    return preg_match($pattern, $xml, $m) ? $m[1] : null;
}

// Map resource identifier => [type, href].
function _cc_resources(string $xml): array {
    $out = [];
    if (preg_match('#<resources\b[^>]*>(.*?)</resources>#is', $xml, $rm)) {
        if (preg_match_all('#<resource\b([^>]*)>#is', $rm[1], $mm, PREG_SET_ORDER)) {
            foreach ($mm as $r) {
                $attrs = $r[1];
                $id = _cc_attr($attrs, 'identifier');
                if ($id === '') continue;
                $type = _cc_attr($attrs, 'type');
                $href = _cc_attr($attrs, 'href');
                if ($href === '') {
                    // href may be on a nested <file> — grab the resource block.
                    if (preg_match('#<resource\b[^>]*\bidentifier="' . preg_quote($id, '#') . '".*?<file\b[^>]*\bhref="([^"]+)"#is', $xml, $fm)) {
                        $href = $fm[1];
                    }
                }
                $out[$id] = ['type' => $type, 'href' => $href];
            }
        }
    }
    return $out;
}

function _cc_attr(string $attrs, string $name): string {
    return preg_match('#\b' . preg_quote($name, '#') . '="([^"]*)"#i', $attrs, $m) ? $m[1] : '';
}

// Parse the organization <item> tree -> [ ['title'=>, 'lessons'=>[ ['title'=>,'ref'=>], ...] ], ... ].
// Top-level items (below the root wrapper) are modules; their children are lessons.
function _cc_organization(string $xml): array {
    if (!preg_match('#<organization\b[^>]*>(.*?)</organization>#is', $xml, $om)) return [];
    $body = $om[1];
    // Tokenize item-open / item-close / title in document order.
    preg_match_all('#<item\b([^>]*?)(/?)>|</item>|<title[^>]*>(.*?)</title>#is', $body, $tokens, PREG_SET_ORDER);
    $root = ['title' => '', 'ref' => '', 'children' => []];
    $stack = [&$root];
    foreach ($tokens as $t) {
        $full = $t[0];
        if (str_starts_with($full, '</item')) {
            if (count($stack) > 1) array_pop($stack);
        } elseif (str_starts_with($full, '<item')) {
            $attrs = $t[1];
            $node = ['title' => '', 'ref' => _cc_attr($attrs, 'identifierref'), 'children' => []];
            $parent = &$stack[count($stack) - 1];
            $parent['children'][] = $node;
            if (($t[2] ?? '') !== '/') {   // not self-closing
                $stack[] = &$parent['children'][count($parent['children']) - 1];
            }
            unset($parent);
        } elseif (isset($t[3])) {          // <title>
            $stack[count($stack) - 1]['title'] = trim(html_entity_decode(strip_tags($t[3]), ENT_QUOTES, 'UTF-8'));
        }
    }
    // The CC root organization usually has a single wrapper item; unwrap it.
    $top = $root['children'];
    if (count($top) === 1 && $top[0]['children']) $top = $top[0]['children'];

    $modules = [];
    foreach ($top as $node) {
        if ($node['children']) {
            $lessons = [];
            foreach ($node['children'] as $c) {
                // flatten one more level if a lesson has sub-items
                if ($c['children'] && !$c['ref']) {
                    foreach ($c['children'] as $cc) $lessons[] = ['title' => $cc['title'], 'ref' => $cc['ref']];
                } else {
                    $lessons[] = ['title' => $c['title'], 'ref' => $c['ref']];
                }
            }
            $modules[] = ['title' => $node['title'], 'lessons' => $lessons];
        } else {
            // A top-level leaf item => a lesson in a default module.
            $modules[] = ['title' => $node['title'] ?: 'Content', 'lessons' => [['title' => $node['title'], 'ref' => $node['ref']]]];
        }
    }
    return $modules;
}

// Load a web-content HTML file, keep the <body>, copy referenced local media.
function _cc_lesson_content(string $base, string $href, string $mediaDir, array &$report): string {
    $file = package_file($base, $href);
    if ($file === null) { $report['skipped'][] = 'Missing file: ' . $href; return ''; }
    $html = (string) file_get_contents($file);
    if (preg_match('#<body\b[^>]*>(.*?)</body>#is', $html, $m)) $html = $m[1];
    // Copy locally-referenced images/media into media/, rewriting src/href. Canvas
    // references files with a (URL-encoded) $IMS-CC-FILEBASE$ token that resolves
    // against the package's web_resources/ folder; other CCs place them beside the HTML.
    $srcDir = dirname($file);
    $html = preg_replace_callback('#(src|href)="([^"]+)"#i', function ($mm) use ($base, $srcDir, $mediaDir, &$report) {
        $raw = $mm[2];
        if (preg_match('~^(https?:|mailto:|data:|#)~i', $raw)) return $mm[0];   // external/anchor: leave
        $u = urldecode(html_entity_decode($raw, ENT_QUOTES));                    // %24IMS-CC-FILEBASE%24, %20…
        $u = preg_replace('#^\$IMS-CC-FILEBASE\$/#', '', $u);
        $u = preg_replace('#[?\#].*$#', '', $u);              // drop ?canvas_download=1 etc.
        $u = ltrim(preg_replace('#^(\.\./)+#', '', $u), '/');
        if ($u === '') return $mm[0];
        $cands = [$base . '/web_resources/' . $u, $base . '/' . $u, $srcDir . '/' . $u];
        $found = null;
        foreach ($cands as $c) {
            $resolved = realpath($c);
            if ($resolved !== false && str_starts_with($resolved, realpath($base) . DIRECTORY_SEPARATOR) && is_file($resolved)) { $found = $resolved; break; }
        }
        if (!$found) { $report['missing_media'] = ($report['missing_media'] ?? 0) + 1; return $mm[0]; }
        $name = substr(md5($u), 0, 8) . '-' . basename($u);
        @copy($found, $mediaDir . '/' . $name);
        $report['media']++;
        return $mm[1] . '="media/' . $name . '"';
    }, $html);
    return editor_sanitize_html($html);
}

// Parse a QTI 1.2 assessment into {pass, questions:[{text,multiple,options:[{text,correct}]}]}.
function _cc_parse_qti(string $xml): array {
    $questions = [];
    if (preg_match_all('#<item\b.*?</item>#is', $xml, $items)) {
        foreach ($items[0] as $item) {
            $text = '';
            if (preg_match('#<presentation\b.*?<mattext[^>]*>(.*?)</mattext>#is', $item, $tm)) {
                $text = trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES, 'UTF-8'));
            }
            $multiple = (bool) preg_match('#rcardinality="Multiple"#i', $item);
            $options = []; $idMap = [];
            if (preg_match_all('#<response_label\b[^>]*\bident="([^"]+)"[^>]*>.*?<mattext[^>]*>(.*?)</mattext>#is', $item, $lm, PREG_SET_ORDER)) {
                foreach ($lm as $i => $l) {
                    $idMap[$l[1]] = $i;
                    $options[] = ['text' => trim(html_entity_decode(strip_tags($l[2]), ENT_QUOTES, 'UTF-8')), 'correct' => false];
                }
            }
            // Correct answers: <varequal ...>RID</varequal> inside resprocessing.
            if (preg_match('#<resprocessing\b.*?</resprocessing>#is', $item, $rp)) {
                if (preg_match_all('#<varequal[^>]*>(.*?)</varequal>#is', $rp[0], $vm)) {
                    foreach ($vm[1] as $rid) {
                        $rid = trim($rid);
                        if (isset($idMap[$rid])) $options[$idMap[$rid]]['correct'] = true;
                    }
                }
            }
            if ($text !== '' && array_filter($options, fn($o) => $o['correct'])) {
                if (count(array_filter($options, fn($o) => $o['correct'])) > 1) $multiple = true;
                $questions[] = ['text' => $text, 'multiple' => $multiple, 'options' => $options];
            }
        }
    }
    return ['pass' => 70, 'questions' => $questions];
}

function _cc_unique_slug(string $slug): string {
    $slug = $slug ?: 'imported-course';
    $root = rtrim(lms_config()['courses_dir'], '/');
    $s = $slug; $n = 2;
    while (is_dir("$root/$s")) { $s = $slug . '-' . $n; $n++; }
    return $s;
}

function package_file(string $base, string $reference): ?string {
    $reference = rawurldecode($reference);
    if (!archive_path_safe($reference)) return null;
    $root = realpath($base); $file = realpath($base . '/' . $reference);
    return $root !== false && $file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR)
        && is_file($file) ? $file : null;
}
