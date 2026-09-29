<?php
// GUI (block-based) course editor backend.
//
// A course is still stored as course.json (so the existing reader is unchanged).
// The editor adds a structured `blocks` array to each lesson/page; on save we
// COMPILE those blocks into the lesson's `content` HTML that the reader renders.
// Existing markdown-built courses (no blocks) open as a single rich-text block.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/creator.php';   // cr_md_to_html(), cr_embed_html(), cr_video_html()
require_once __DIR__ . '/discovery.php'; // scan_courses()

function editor_course_dir(string $slug): string {
    return rtrim(lms_config()['courses_dir'], '/') . '/' . $slug;
}
function editor_media_dir(string $slug): string {
    $d = editor_course_dir($slug) . '/media';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

// Load a course for editing. Ensures every lesson has a `blocks` array (legacy
// courses get one rich-text block holding their existing HTML content).
function editor_load(string $slug): ?array {
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) return null;
    $path = editor_course_dir($slug) . '/course.json';
    if (!is_file($path)) return null;
    $course = json_decode((string) file_get_contents($path), true);
    if (!is_array($course)) return null;
    // NB: iterate the real arrays by reference (not `$x ?? []`, which copies).
    if (!empty($course['modules']) && is_array($course['modules'])) {
        foreach ($course['modules'] as &$m) {
            if (empty($m['lessons']) || !is_array($m['lessons'])) continue;
            foreach ($m['lessons'] as &$l) {
                if (empty($l['blocks']) || !is_array($l['blocks'])) {
                    $l['blocks'] = editor_blocks_from_content((string) ($l['content'] ?? ''));
                }
            }
            unset($l);
        }
        unset($m);
    }
    return $course;
}

// Wrap legacy HTML content as a single editable rich-text block.
function editor_blocks_from_content(string $html): array {
    $html = trim($html);
    if ($html === '') return [];
    return [['type' => 'richtext', 'html' => $html]];
}

// --- HTML sanitize (trusted-but-defensive: course devs can't inject script) --
//
// This is a tag/attribute ALLOWLIST sanitizer, not a blacklist regex filter.
// A prior blacklist-regex version was bypassed by `<img/onerror=...>` — the
// slash right after the tag name is an attribute separator per the HTML5
// tokenizer (browsers reconsume it in "before attribute name" state), but the
// old regex required a literal space before "on", so it never matched. That
// bug class (regexes guessing at tag/attribute boundaries instead of actually
// parsing them) is why this version hand-parses tags char-by-char instead:
// whitespace AND '/' are treated as attribute separators, matching what
// browsers actually do, so there's no boundary confusion for an attacker to
// exploit. (PHP's DOMDocument would also solve this, but isn't guaranteed to
// be compiled into every deployment target, so this has zero dependencies.)
function editor_sanitize_html(string $html): string {
    if (trim($html) === '') return '';

    $allowedTags = ['p', 'br', 'div', 'span', 'b', 'strong', 'i', 'em', 'u', 's', 'strike',
        'sub', 'sup', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'a', 'img',
        'blockquote', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'hr', 'code', 'pre',
        'font', 'iframe'];
    $voidTags = ['br', 'hr', 'img'];
    $dropContentTags = ['script', 'style', 'object', 'embed', 'form', 'input', 'link', 'meta'];
    $globalAttrs = ['style'];
    $tagAttrs = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'font' => ['color', 'size'],
        // https-only, and no srcdoc (which would execute script in our origin) —
        // kept tight since this is the one tag that embeds a whole other document.
        'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder'],
    ];

    $out = '';
    $stack = [];   // names of tags actually emitted open, so a rejected/unopened
                    // tag's stray closing tag (e.g. a rejected iframe's </iframe>)
                    // never appears in the output.
    $len = strlen($html);
    $i = 0;
    while ($i < $len) {
        $lt = strpos($html, '<', $i);
        if ($lt === false) { $out .= substr($html, $i); break; }
        if ($lt > $i) $out .= substr($html, $i, $lt - $i);

        $j = $lt + 1;
        $isClose = false;
        if ($j < $len && $html[$j] === '/') { $isClose = true; $j++; }

        // Comments and doctype/processing-instruction markup: drop entirely.
        if (substr($html, $j, 3) === '!--') {
            $end = strpos($html, '-->', $j);
            $i = $end === false ? $len : $end + 3;
            continue;
        }
        if (isset($html[$j]) && ($html[$j] === '!' || $html[$j] === '?')) {
            $end = strpos($html, '>', $j);
            $i = $end === false ? $len : $end + 1;
            continue;
        }

        $nameStart = $j;
        while ($j < $len && ctype_alnum($html[$j])) $j++;
        $tag = strtolower(substr($html, $nameStart, $j - $nameStart));
        if ($tag === '') {
            // Not a real tag start (e.g. a stray '<' in text) — emit it encoded
            // so the browser never mistakes it for the start of markup.
            $out .= '&lt;';
            $i = $lt + 1;
            continue;
        }

        // Parse attributes up to the real '>' — respecting quoted attribute
        // values (so a '>' inside title="1>2" doesn't end the tag early).
        $attrs = [];
        while ($j < $len && $html[$j] !== '>') {
            // Whitespace AND '/' both act as separators here (see note above).
            if (ctype_space($html[$j]) || $html[$j] === '/') { $j++; continue; }
            $anStart = $j;
            while ($j < $len && !ctype_space($html[$j]) && !in_array($html[$j], ['/', '=', '>'], true)) $j++;
            $attrName = strtolower(substr($html, $anStart, $j - $anStart));
            while ($j < $len && ctype_space($html[$j])) $j++;
            $attrVal = '';
            if ($j < $len && $html[$j] === '=') {
                $j++;
                while ($j < $len && ctype_space($html[$j])) $j++;
                if ($j < $len && ($html[$j] === '"' || $html[$j] === "'")) {
                    $q = $html[$j]; $j++;
                    $qend = strpos($html, $q, $j);
                    if ($qend === false) { $attrVal = substr($html, $j); $j = $len; }
                    else { $attrVal = substr($html, $j, $qend - $j); $j = $qend + 1; }
                } else {
                    $vStart = $j;
                    while ($j < $len && !ctype_space($html[$j]) && $html[$j] !== '>') $j++;
                    $attrVal = substr($html, $vStart, $j - $vStart);
                }
            }
            if ($attrName !== '' && !isset($attrs[$attrName])) $attrs[$attrName] = html_entity_decode($attrVal, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if ($j < $len) $j++;   // consume the '>'
        $i = $j;

        if (in_array($tag, $dropContentTags, true)) {
            if (!$isClose) {
                // Drop everything up to (and including) the matching close tag,
                // so e.g. <script>...</script> doesn't leave its body as visible text.
                $closeAt = stripos($html, '</' . $tag, $i);
                $i = $closeAt === false ? $len : $closeAt + strlen('</' . $tag) + 1;
                if ($closeAt !== false) { $gt = strpos($html, '>', $closeAt); $i = $gt === false ? $len : $gt + 1; }
            }
            continue;
        }
        if (!in_array($tag, $allowedTags, true)) continue;   // unwrap: drop the tag, keep its (already-parsed-separately) content

        if ($isClose) {
            if (!in_array($tag, $voidTags, true)) {
                // Only emit a closing tag that actually has an open one pending —
                // drops stray closes (e.g. a rejected iframe's </iframe>) instead
                // of leaking them into the output, and un-nests intervening tags
                // if the input was malformed (e.g. <b><i></b></i>).
                $pos = array_search($tag, array_reverse($stack, true), true);
                if ($pos !== false) {
                    while (count($stack) > $pos) { $out .= '</' . array_pop($stack) . '>'; }
                }
            }
            continue;
        }

        if ($tag === 'iframe') {
            $src = $attrs['src'] ?? '';
            if (isset($attrs['srcdoc']) || !preg_match('#^https://#i', trim($src))) continue;
        }

        $keep = array_merge($globalAttrs, $tagAttrs[$tag] ?? []);
        $rendered = '';
        foreach ($attrs as $an => $av) {
            if (!in_array($an, $keep, true)) continue;
            if ($an === 'href') {
                // Links (unlike img/iframe src) legitimately use mailto:/tel: —
                // safe_media_ref() alone would silently drop those.
                $av = trim($av);
                if (!preg_match('#^(mailto|tel):#i', $av)) $av = safe_media_ref($av);
                if ($av === '') continue;
            } elseif ($an === 'src') {
                $av = safe_media_ref($av);
                if ($av === '') continue;
            } elseif ($an === 'style') {
                $av = editor_sanitize_style($av);
                if ($av === '') continue;
            }
            $rendered .= ' ' . $an . '="' . htmlspecialchars($av, ENT_QUOTES) . '"';
        }
        $out .= '<' . $tag . $rendered . (in_array($tag, $voidTags, true) ? ' /' : '') . '>';
        if (!in_array($tag, $voidTags, true)) $stack[] = $tag;
    }
    while ($stack) { $out .= '</' . array_pop($stack) . '>'; }   // close anything left unclosed
    return trim($out);
}

// Allowlist-based style-attribute sanitizer: only a small set of properties
// the rich-text toolbar and paste-cleaner actually produce, and only values
// that can't smuggle a URL scheme or an old IE `expression()` attack.
function editor_sanitize_style(string $style): string {
    $safeProps = ['color', 'background-color', 'font-weight', 'font-style', 'text-decoration',
        'text-decoration-line', 'text-align', 'margin-left', 'margin', 'vertical-align',
        'border', 'border-collapse', 'padding', 'max-width', 'width', 'height'];
    $out = [];
    foreach (explode(';', $style) as $decl) {
        $decl = trim($decl);
        if ($decl === '' || !str_contains($decl, ':')) continue;
        [$prop, $val] = array_map('trim', explode(':', $decl, 2));
        $prop = strtolower($prop);
        if (!in_array($prop, $safeProps, true)) continue;
        if (preg_match('/expression|url\s*\(|javascript|vbscript|@import|[<>]/i', $val)) continue;
        $out[] = $prop . ':' . $val;
    }
    return implode(';', $out);
}

// --- Block compilation (blocks -> reader HTML) -------------------------------

// Extended embed: OneDrive/SharePoint + Dropbox on top of creator's coverage
// (YouTube, Vimeo, Google Drive/Docs, direct MP4).
function editor_embed_html(string $url): string {
    $u = trim($url);
    if ($u === '') return '';
    // Dropbox: serve raw content; play media inline, otherwise iframe the preview.
    if (preg_match('#https?://(?:www\.)?dropbox\.com/#i', $u)) {
        $raw = preg_replace('#([?&])dl=\d#', '$1raw=1', $u);
        if (!preg_match('#[?&]raw=1#', $raw)) $raw .= (str_contains($raw, '?') ? '&' : '?') . 'raw=1';
        if (preg_match('#\.(mp4|webm|m4v)(\?\S*)?$#i', $u)) {
            return '<div class="video-embed"><video controls preload="metadata" playsinline><source src="'
                . htmlspecialchars($raw) . '" /></video></div>';
        }
        return '<div class="doc-embed"><iframe src="' . htmlspecialchars($raw)
            . '" title="Embedded file" loading="lazy" frameborder="0" allowfullscreen></iframe></div>';
    }
    // OneDrive / SharePoint: iframe the share link (author should use the "Embed"
    // share option; we append the embed hint for onedrive.live.com links).
    if (preg_match('#https?://(?:[\w-]+\.)?(1drv\.ms|onedrive\.live\.com|sharepoint\.com)/#i', $u)) {
        $src = $u;
        if (str_contains($u, 'onedrive.live.com') && !str_contains($u, 'embed')) {
            $src = preg_replace('#/(view|redir)\b#', '/embed', $u);
            if (!str_contains($src, 'embed')) $src .= (str_contains($src, '?') ? '&' : '?') . 'em=2';
        }
        return '<div class="doc-embed"><iframe src="' . htmlspecialchars($src)
            . '" title="Embedded document" loading="lazy" frameborder="0" allowfullscreen></iframe></div>';
    }
    // Known providers via creator (YouTube/Vimeo/Google/MP4). If it fell through
    // to a plain link but the URL is https, embed the page in an iframe instead.
    $out = cr_embed_html($u);
    if (strpos($out, '<iframe') === false && strpos($out, '<video') === false && preg_match('#^https://#i', $u)) {
        return '<div class="doc-embed"><iframe src="' . htmlspecialchars($u)
            . '" title="Embedded page" loading="lazy" style="width:100%;min-height:640px;border:0" allowfullscreen></iframe></div>';
    }
    return $out;
}

// Compile one block to HTML. Collects video specs into $videos (by reference)
// for the lesson's `videos` array + stats.
function editor_compile_block(array $b, array &$videos): string {
    switch ($b['type'] ?? '') {
        case 'richtext':
            return editor_sanitize_html((string) ($b['html'] ?? ''));
        case 'markdown':
            return cr_md_to_html((string) ($b['md'] ?? ''));
        case 'heading':
            $lvl = max(2, min(4, (int) ($b['level'] ?? 2)));
            return "<h$lvl>" . htmlspecialchars((string) ($b['text'] ?? '')) . "</h$lvl>";
        case 'image':
            $src = htmlspecialchars(safe_media_ref((string) ($b['src'] ?? '')));
            if ($src === '') return '';
            $alt = htmlspecialchars((string) ($b['alt'] ?? ''));
            $cap = trim((string) ($b['caption'] ?? ''));
            $align = in_array($b['align'] ?? 'full', ['full', 'left', 'center', 'right'], true) ? ($b['align'] ?? 'full') : 'full';
            $wrap = !empty($b['wrap']) && ($align === 'left' || $align === 'right');
            if ($wrap) {
                $fstyle = $align === 'left'
                    ? 'float:left;margin:.25rem 1.25rem 1rem 0;max-width:48%'
                    : 'float:right;margin:.25rem 0 1rem 1.25rem;max-width:48%';
                $imgMargin = '';
            } else {
                $fstyle = 'display:block;margin:1rem 0';
                $imgMargin = $align === 'center' ? 'margin-left:auto;margin-right:auto;'
                    : ($align === 'right' ? 'margin-left:auto;' : ($align === 'left' ? 'margin-right:auto;' : ''));
            }
            $imgStyle = ($align === 'full' ? 'width:100%;' : 'max-width:100%;') . 'height:auto;display:block;' . $imgMargin;
            $fig = '<figure class="course-image align-' . $align . ($wrap ? ' wrap' : '') . '" style="' . $fstyle . '">'
                 . '<img src="' . $src . '" alt="' . $alt . '" loading="lazy" style="' . $imgStyle . '" />';
            if ($cap !== '') $fig .= '<figcaption style="font-size:.85rem;color:#555;margin-top:4px">' . htmlspecialchars($cap) . '</figcaption>';
            return $fig . '</figure>';
        case 'video':
            $mode = $b['mode'] ?? 'embed';
            if ($mode === 'file' && !empty($b['file'])) {
                $v = ['provider' => 'local', 'file' => (string) $b['file']];
                $videos[] = $v;
                return cr_video_html($v);
            }
            $url = (string) ($b['url'] ?? '');
            if ($url === '') return '';
            $spec = cr_video_spec($url);
            if ($spec) $videos[] = $spec;
            return editor_embed_html($url);
        case 'file':
            $href = htmlspecialchars(safe_media_ref((string) ($b['href'] ?? '')));
            if ($href === '') return '';
            $name = htmlspecialchars((string) ($b['name'] ?? 'Download'));
            $desc = trim((string) ($b['desc'] ?? ''));
            return '<p class="file-attach"><a href="' . $href . '" target="_blank" rel="noopener">📎 '
                . $name . '</a>' . ($desc !== '' ? ' — ' . htmlspecialchars($desc) : '') . '</p>';
        case 'callout':
            return '<div class="callout">' . editor_sanitize_html((string) ($b['html'] ?? '')) . '</div>';
        case 'divider':
            return '<hr />';
    }
    return '';
}

// Compile a lesson's blocks into [contentHtml, videos].
function editor_compile_blocks(array $blocks): array {
    $videos = []; $parts = [];
    foreach ($blocks as $b) {
        if (!is_array($b)) continue;
        $html = editor_compile_block($b, $videos);
        if ($html !== '') $parts[] = $html;
    }
    return [implode("\n", $parts), $videos];
}

// --- Save --------------------------------------------------------------------

// Persist an edited course structure. $incoming is the editor's JSON: title,
// tagline, and modules[] -> lessons[] with blocks[] (+ optional quiz). Recompiles
// content, recomputes stats, writes course.json, and rescans so the catalog +
// completion totals update. Returns [ok, messageOrSlug].
// ---------------------------------------------------------------------------
//  Concurrent-edit protection: a soft editor lock/lease + a revision guard so
//  two admins/course-developers editing the same course can't silently clobber
//  each other. Locks are lightweight JSON files under <data>/editlocks/; the
//  revision counter lives inside course.json. No schema/DB change.
// ---------------------------------------------------------------------------
const EDITOR_LOCK_TTL = 90;   // a lease stays live for this many seconds per heartbeat

function editor_lock_dir(): string {
    $d = rtrim(lms_config()['data_dir'], '/') . '/editlocks';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}
function editor_lock_path(string $slug): string {
    return editor_lock_dir() . '/' . preg_replace('/[^a-z0-9_-]/', '', $slug) . '.json';
}
/** The current live lock holder, or null if none / the lease has expired. */
function editor_lock_read(string $slug): ?array {
    $f = editor_lock_path($slug);
    if (!is_file($f)) return null;
    $l = json_decode((string) @file_get_contents($f), true);
    if (!is_array($l) || empty($l['user_id'])) return null;
    if (time() - (int) ($l['heartbeat_at'] ?? 0) > EDITOR_LOCK_TTL) return null;   // stale lease
    return $l;
}
function editor_lock_name(array $user): string {
    return trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''))
        ?: (string) ($user['email'] ?? 'Editor');
}
/** Acquire or renew. [true,$lock] if we hold it; [false,$holder] if someone else does and !$force. */
function editor_lock_acquire(string $slug, array $user, bool $force = false): array {
    $cur = editor_lock_read($slug);
    $uid = (int) $user['id'];
    if ($cur && (int) $cur['user_id'] !== $uid && !$force) return [false, $cur];
    $lock = [
        'user_id'      => $uid,
        'name'         => editor_lock_name($user),
        'acquired_at'  => ($cur && (int) $cur['user_id'] === $uid) ? (int) $cur['acquired_at'] : time(),
        'heartbeat_at' => time(),
    ];
    @file_put_contents(editor_lock_path($slug), json_encode($lock));
    return [true, $lock];
}
function editor_lock_heartbeat(string $slug, int $uid): bool {
    $cur = editor_lock_read($slug);
    if (!$cur || (int) $cur['user_id'] !== $uid) return false;   // lost / taken over
    $cur['heartbeat_at'] = time();
    @file_put_contents(editor_lock_path($slug), json_encode($cur));
    return true;
}
function editor_lock_release(string $slug, int $uid): void {
    $cur = editor_lock_read($slug);
    if ($cur && (int) $cur['user_id'] === $uid) @unlink(editor_lock_path($slug));
}
/** Snapshot the previous course.json (off-web, under <data>) before overwriting. */
function editor_backup_snapshot(string $slug, array $existing): void {
    if (!$existing) return;
    $bdir = rtrim(lms_config()['data_dir'], '/') . '/course-revisions/' . preg_replace('/[^a-z0-9_-]/', '', $slug);
    if (!is_dir($bdir)) @mkdir($bdir, 0775, true);
    $rev = (int) ($existing['rev'] ?? 0);
    @file_put_contents($bdir . '/rev' . str_pad((string) $rev, 5, '0', STR_PAD_LEFT) . '-' . date('Ymd-His') . '.json',
        json_encode($existing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $snaps = glob($bdir . '/*.json') ?: [];
    if (count($snaps) > 30) { sort($snaps); foreach (array_slice($snaps, 0, count($snaps) - 30) as $o) @unlink($o); }
}

// $baseRev = the revision the editor loaded (null skips the check). $user = the
// saver (enables the lock check + stamps updated_by). Failure result may be a
// string (simple 422) or an array {error,code,conflict,lockedBy} for conflicts.
function editor_save(string $slug, array $incoming, ?int $baseRev = null, ?array $user = null): array {
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) return [false, 'Invalid course.'];
    $dir = editor_course_dir($slug);
    if (!is_file("$dir/course.json")) return [false, 'Course not found.'];
    $existing = json_decode((string) file_get_contents("$dir/course.json"), true) ?: [];
    $existingRev = (int) ($existing['rev'] ?? 0);

    // Refuse if someone else holds a live editing lease.
    if ($user) {
        $holder = editor_lock_read($slug);
        if ($holder && (int) $holder['user_id'] !== (int) $user['id']) {
            return [false, ['error' => $holder['name'] . ' is currently editing this course, so your changes were not saved.',
                            'code' => 409, 'conflict' => true, 'lockedBy' => $holder['name']]];
        }
    }
    // Optimistic concurrency: refuse a save built on a stale copy.
    if ($baseRev !== null && $baseRev !== $existingRev) {
        return [false, ['error' => 'This course was changed by ' . ((string) ($existing['updated_by'] ?? 'someone else'))
                            . ' since you opened it. Reload to get the latest version, then re-apply your edits.',
                        'code' => 409, 'conflict' => true, 'rev' => $existingRev]];
    }

    $title = trim((string) ($incoming['title'] ?? $existing['title'] ?? ''));
    if ($title === '') return [false, 'The course needs a title.'];

    $outModules = [];
    $lessonCount = 0; $topicCount = 0; $videoCount = 0; $quizCount = 0;
    foreach ($incoming['modules'] ?? [] as $mi => $m) {
        $lessons = [];
        foreach ($m['lessons'] ?? [] as $li => $l) {
            $blocks = is_array($l['blocks'] ?? null) ? $l['blocks'] : [];
            [$html, $videos] = editor_compile_blocks($blocks);
            $lesson = [
                'id'      => (string) ($l['id'] ?? "lesson-$mi-$li"),
                'title'   => trim((string) ($l['title'] ?? 'Untitled page')),
                'slug'    => cr_slugify((string) ($l['title'] ?? "page-$mi-$li")),
                'content' => $html,
                'blocks'  => array_values($blocks),   // keep the editable source
                'videos'  => $videos,
                'topics'  => array_values($l['topics'] ?? []),
            ];
            $topicCount += count($lesson['topics']);
            $videoCount += count($videos);
            if (!empty($l['quiz']['questions'])) {
                $quiz = $l['quiz'];
                $quiz['id'] = (string) ($quiz['id'] ?? "quiz-$mi-$li");
                $lesson['quiz'] = $quiz;
                $quizCount++;
            }
            $lessons[] = $lesson;
            $lessonCount++;
        }
        $key = (string) ($m['key'] ?? (string) $mi);
        $outModules[] = [
            'id'        => (string) ($m['id'] ?? 'module-' . $key),
            'key'       => $key,
            'label'     => (string) ($m['label'] ?? ('Module ' . ($mi + 1))),
            'title'     => trim((string) ($m['title'] ?? ('Module ' . ($mi + 1)))),
            'summary'   => (string) ($m['summary'] ?? ''),
            // Named discussion forums added to this module (like pages). Each may
            // carry a block-based discussion prompt, compiled to HTML like a page.
            'forums'    => array_values(array_map(
                function ($f) {
                    $blocks = is_array($f['blocks'] ?? null) ? $f['blocks'] : [];
                    [$html] = editor_compile_blocks($blocks);
                    return ['id' => (string) ($f['id'] ?? ''), 'name' => trim((string) ($f['name'] ?? '')) ?: 'Discussion',
                            'blocks' => array_values($blocks), 'content' => $html];
                },
                array_filter(is_array($m['forums'] ?? null) ? $m['forums'] : [],
                    fn($f) => is_array($f) && ($f['id'] ?? '') !== ''))),
            'lessons'   => $lessons,
        ];
    }

    $course = $existing;
    $course['slug']    = $slug;
    $course['title']   = $title;
    $course['tagline'] = trim((string) ($incoming['tagline'] ?? $existing['tagline'] ?? ''));
    $course['modules'] = $outModules;
    $course['editor']  = 'blocks';    // mark as GUI-authored (course.json is truth)
    $course['stats']   = [
        'modules'      => count($outModules),
        'core_modules' => count(array_filter($outModules, fn($m) => str_starts_with((string) $m['label'], 'Module'))),
        'lessons'      => $lessonCount,
        'topics'       => $topicCount,
        'videos'       => $videoCount,
        'quizzes'      => $quizCount,
    ];

    // Bump the revision + stamp who saved (drives the conflict guard above).
    $course['rev']        = $existingRev + 1;
    $course['updated_at'] = time();
    if ($user) $course['updated_by'] = editor_lock_name($user);

    $json = json_encode($course, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return [false, 'Could not encode the course.'];
    // Snapshot the old version (recoverable), then write atomically so a reader
    // or the other editor never sees a half-written course.json.
    editor_backup_snapshot($slug, $existing);
    $tmp = "$dir/.course.json.tmp";
    if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, "$dir/course.json")) {
        @unlink($tmp);
        return [false, 'Could not write course.json (check permissions).'];
    }
    if ($user) editor_lock_acquire($slug, $user, true);   // keep/refresh our lease

    // Ensure the reader shell exists so the course renders at /courses/<slug>/.
    if (!is_file("$dir/index.html") && function_exists('cr_write_shell')) {
        cr_write_shell($dir, $title, (string) $course['tagline']);
    }
    if (function_exists('scan_courses')) scan_courses();   // refresh title + total_units
    return [true, ['slug' => $slug, 'rev' => (int) $course['rev']]];
}

// Create a blank GUI course and return its slug (or [false, error]).
function editor_create_blank(string $title): array {
    $title = trim($title);
    if ($title === '') return [false, 'Enter a course title.'];
    $slug = cr_slugify($title);
    if ($slug === '') return [false, 'Enter a valid title.'];
    if (in_array($slug, ['sapiqo', 'sapiqo-data', 'assets', 'creator', 'content'], true)) return [false, 'That name is reserved.'];
    $dir = editor_course_dir($slug);
    if (is_dir($dir)) return [false, 'A course with that name already exists.'];
    @mkdir($dir . '/media/videos', 0775, true);
    $course = [
        'slug' => $slug, 'title' => $title, 'provider' => lms_config()['org_name'] ?? '', 'tagline' => '',
        'tags' => [], 'overview_html' => '', 'editor' => 'blocks',
        'stats' => ['modules' => 1, 'core_modules' => 1, 'lessons' => 1, 'topics' => 0, 'videos' => 0, 'quizzes' => 0],
        'modules' => [[
            'id' => 'module-0', 'key' => '0', 'label' => 'Module 1', 'title' => 'Module 1', 'summary' => '',
            'lessons' => [['id' => 'lesson-0-0', 'title' => 'Untitled page', 'slug' => 'untitled-page',
                'content' => '', 'blocks' => [], 'videos' => [], 'topics' => []]],
        ]],
    ];
    file_put_contents($dir . '/course.json',
        json_encode($course, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    if (function_exists('cr_write_shell')) cr_write_shell($dir, $title, '');
    if (function_exists('scan_courses')) scan_courses();
    return [true, $slug];
}

// --- Resource center (per-course files) --------------------------------------

// A resource is any file under the course's media/ tree. Metadata (labels) lives
// in media/resources.json keyed by relative path.
function editor_resources_meta(string $slug): array {
    $f = editor_media_dir($slug) . '/resources.json';
    return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
}
function editor_resources_meta_save(string $slug, array $meta): void {
    @file_put_contents(editor_media_dir($slug) . '/resources.json',
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function editor_resource_kind(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, ['png','jpg','jpeg','gif','webp','svg'], true)) return 'image';
    if (in_array($ext, ['mp4','webm','m4v','mov'], true)) return 'video';
    if (in_array($ext, ['vtt','srt'], true)) return 'caption';
    if (in_array($ext, ['pdf'], true)) return 'pdf';
    if (in_array($ext, ['doc','docx','ppt','pptx','xls','xlsx','odt','txt','csv'], true)) return 'doc';
    return 'file';
}

// List a course's resources (relative path, kind, size, label).
function editor_resources(string $slug): array {
    $media = editor_media_dir($slug);
    if (!is_dir($media)) return [];
    $meta = editor_resources_meta($slug);
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($media, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($it as $f) {
        $rel = 'media/' . str_replace('\\', '/', ltrim(substr($f->getPathname(), strlen($media)), '/\\'));
        if (str_ends_with($rel, 'resources.json')) continue;
        $out[] = [
            'path'  => $rel,
            'name'  => basename($rel),
            'kind'  => editor_resource_kind($rel),
            'size'  => $f->getSize(),
            'label' => $meta[$rel]['label'] ?? '',
        ];
    }
    usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $out;
}

// Store an uploaded file into the course's media/ (videos into media/videos).
// Returns [ok, relPathOrError].
function editor_store_upload(string $slug, array $file, string $label = ''): array {
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) return [false, 'Invalid course.'];
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return [false, 'No file uploaded.'];
    $orig = (string) ($file['name'] ?? 'file');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['png','jpg','jpeg','gif','webp','svg','mp4','webm','m4v','mov','vtt','srt','pdf',
                'doc','docx','ppt','pptx','xls','xlsx','odt','txt','csv'];
    if (!in_array($ext, $allowed, true)) return [false, 'That file type is not allowed.'];
    $base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($orig, PATHINFO_FILENAME));
    $base = trim($base, '-') ?: 'file';
    $kind = editor_resource_kind($orig);
    $sub = $kind === 'video' ? '/videos' : '';
    $destDir = editor_media_dir($slug) . $sub;
    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
    $fname = $base . '.' . $ext;
    $i = 1;
    while (is_file("$destDir/$fname")) { $fname = $base . '-' . (++$i) . '.' . $ext; }
    if (!move_uploaded_file($file['tmp_name'], "$destDir/$fname")) return [false, 'Could not save the upload.'];
    $rel = 'media' . $sub . '/' . $fname;
    if ($label !== '') {
        $meta = editor_resources_meta($slug); $meta[$rel] = ['label' => $label];
        editor_resources_meta_save($slug, $meta);
    }
    return [true, $rel];
}

function editor_delete_resource(string $slug, string $rel): bool {
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) return false;
    // Confine to the course's media/ tree; no traversal.
    if (!str_starts_with($rel, 'media/') || str_contains($rel, '..')) return false;
    $abs = editor_course_dir($slug) . '/' . $rel;
    $real = realpath($abs); $mediaReal = realpath(editor_media_dir($slug));
    if ($real === false || $mediaReal === false || !str_starts_with($real, $mediaReal)) return false;
    @unlink($real);
    $meta = editor_resources_meta($slug); unset($meta[$rel]); editor_resources_meta_save($slug, $meta);
    return true;
}
