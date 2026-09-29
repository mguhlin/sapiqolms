<?php
// In-app Help center: renders the Markdown manual under docs/manual/ as web pages.
// Pages are trusted, shipped-with-the-app content (not user input); the renderer
// still escapes text nodes and whitelists link targets. Access is role-filtered:
// learner pages for everyone signed in, authoring pages for course developers,
// admin pages for administrators.

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function help_dir(): string {
    return dirname(__DIR__) . '/docs/manual';
}

// Directory holding the current locale's translated manual, e.g.
// docs/manual/es — or the English base dir for English/unknown locales.
// Any page missing a translation there falls back to the English file.
function help_locale_dir(): string {
    $loc = function_exists('current_locale') ? current_locale() : 'en';
    if ($loc === 'en') return help_dir();
    $d = help_dir() . '/' . $loc;
    return is_dir($d) ? $d : help_dir();
}

// All manual pages with metadata, ordered by their numeric filename prefix.
// The canonical page list (slugs, numbers, tiers) always comes from the English
// manual so the index is identical across locales; only the displayed title is
// taken from the translated file when one exists.
function help_pages(): array {
    static $cache = [];
    $loc = function_exists('current_locale') ? current_locale() : 'en';
    if (isset($cache[$loc])) return $cache[$loc];
    $locDir = help_locale_dir();
    $pages = [];
    foreach (glob(help_dir() . '/*.md') ?: [] as $f) {
        $base = basename($f, '.md');
        if ($base === 'README') continue;
        if (!preg_match('/^(\d+)-/', $base, $m)) continue;
        $num = (int) $m[1];
        // Prefer the translated file's H1 for the title; fall back to English.
        $locFile = $locDir . '/' . $base . '.md';
        $src = is_file($locFile) ? $locFile : $f;
        $txt = (string) file_get_contents($src);
        preg_match('/^#\s+(.+)$/m', $txt, $tm);
        $title = trim($tm[1] ?? $base);
        $tier = $num < 10 ? 'learner' : ($num < 20 ? 'authoring' : 'admin');
        $pages[] = ['slug' => $base, 'num' => $num, 'title' => $title, 'tier' => $tier];
    }
    usort($pages, fn($a, $b) => $a['num'] <=> $b['num']);
    $cache[$loc] = $pages;
    return $pages;
}

// The display sections of the index, in order (num range => label).
function help_sections(): array {
    $t = fn(string $k, string $d) => function_exists('t') ? t($k, $d) : $d;
    return [
        ['min' => 1,  'max' => 9,  'label' => $t('help.sec_learners', 'For learners')],
        ['min' => 10, 'max' => 19, 'label' => $t('help.sec_devs', 'For course developers')],
        ['min' => 20, 'max' => 29, 'label' => $t('help.sec_people', 'Administration — people & access')],
        ['min' => 30, 'max' => 39, 'label' => $t('help.sec_courses', 'Administration — courses & data')],
        ['min' => 40, 'max' => 49, 'label' => $t('help.sec_platform', 'Administration — platform & integrations')],
    ];
}

// May the current user see a given page?
function help_can_see(array $page): bool {
    if ($page['tier'] === 'learner') return true;
    if ($page['tier'] === 'authoring') return function_exists('can_edit_content') && can_edit_content();
    return function_exists('is_admin') && is_admin();
}

function help_find(string $slug): ?array {
    foreach (help_pages() as $p) if ($p['slug'] === $slug) return $p;
    return null;
}

// Where admin-authored overrides of shipped Help pages live — outside the
// code folder (same "update-safe" reasoning as legal_content_html()), so an
// admin's edits via Admin -> Help pages survive a software update.
function help_override_dir(): string {
    $cfg = lms_config();
    return rtrim($cfg['data_dir'] ?? '', '/') . '/help-overrides';
}

// Render one page's Markdown to sanitized HTML. Returns null if not found.
// Resolution order (first hit wins), so a translation is preferred for its
// locale but everything falls back cleanly to English:
//   1. locale-specific admin override  <data>/help-overrides/<loc>/<slug>.html
//   2. translated manual page           docs/manual/<loc>/<slug>.md
//   3. English admin override           <data>/help-overrides/<slug>.html
//   4. shipped English manual page       docs/manual/<slug>.md
// Admin-authored HTML overrides are already sanitized at save time.
function help_render(string $slug): ?string {
    if (!preg_match('/^[0-9a-z-]+$/', $slug)) return null;   // path-traversal guard
    $loc = function_exists('current_locale') ? current_locale() : 'en';
    if ($loc !== 'en') {
        $locOverride = help_override_dir() . '/' . $loc . '/' . $slug . '.html';
        if (is_file($locOverride)) return (string) file_get_contents($locOverride);
        $locFile = help_dir() . '/' . $loc . '/' . $slug . '.md';
        if (is_file($locFile)) return help_md_to_html((string) file_get_contents($locFile));
    }
    $override = help_override_dir() . '/' . $slug . '.html';
    if (is_file($override)) return (string) file_get_contents($override);
    $file = help_dir() . '/' . $slug . '.md';
    if (!is_file($file)) return null;
    return help_md_to_html((string) file_get_contents($file));
}

// --- Markdown -> HTML (the GitHub-flavored subset the manuals use) -----------
// Handles: # .. ###### headings, fenced ``` code blocks, | tables |, - / 1.
// lists, > blockquotes, --- rules, paragraphs, and inline bold/italic/code/
// links/images. Internal links to "NN-name.md" become /help/NN-name.

function help_md_to_html(string $md): string {
    $lines = explode("\n", str_replace("\r\n", "\n", $md));
    $n = count($lines);
    $out = []; $para = [];
    $flush = function () use (&$out, &$para) {
        if ($para) { $out[] = '<p>' . help_inline(trim(implode(' ', $para))) . '</p>'; $para = []; }
    };
    for ($i = 0; $i < $n; ) {
        $line = $lines[$i];
        $s = trim($line);

        // Fenced code block.
        if (preg_match('/^```/', $s)) {
            $flush(); $buf = []; $i++;
            while ($i < $n && !preg_match('/^```/', trim($lines[$i]))) { $buf[] = $lines[$i]; $i++; }
            $i++; // closing fence
            $out[] = '<pre><code>' . htmlspecialchars(implode("\n", $buf), ENT_QUOTES) . '</code></pre>';
            continue;
        }
        if ($s === '') { $flush(); $i++; continue; }
        // Horizontal rule.
        if (preg_match('/^(---|\*\*\*|___)$/', $s)) { $flush(); $out[] = '<hr>'; $i++; continue; }
        // Heading.
        if (preg_match('/^(#{1,6})\s+(.*)$/', $s, $m)) {
            $flush(); $lvl = strlen($m[1]);
            $out[] = "<h$lvl>" . help_inline(trim($m[2])) . "</h$lvl>"; $i++; continue;
        }
        // Table: a header row followed by a |---|---| separator.
        if (str_starts_with($s, '|') && $i + 1 < $n && preg_match('/^\|[\s:|-]+\|?$/', trim($lines[$i + 1]))) {
            $flush();
            $header = help_table_cells($s);
            $i += 2; // header + separator
            $rows = [];
            while ($i < $n && str_starts_with(trim($lines[$i]), '|')) { $rows[] = help_table_cells(trim($lines[$i])); $i++; }
            $h = '<thead><tr>' . implode('', array_map(fn($c) => '<th>' . help_inline($c) . '</th>', $header)) . '</tr></thead>';
            $b = '';
            foreach ($rows as $r) $b .= '<tr>' . implode('', array_map(fn($c) => '<td>' . help_inline($c) . '</td>', $r)) . '</tr>';
            $out[] = '<div class="table-wrap"><table class="table">' . $h . '<tbody>' . $b . '</tbody></table></div>';
            continue;
        }
        // Blockquote.
        if (preg_match('/^>\s?/', $s)) {
            $flush(); $buf = [];
            while ($i < $n && preg_match('/^>\s?/', trim($lines[$i]))) { $buf[] = preg_replace('/^>\s?/', '', trim($lines[$i])); $i++; }
            $out[] = '<blockquote>' . help_md_to_html(implode("\n", $buf)) . '</blockquote>';
            continue;
        }
        // Unordered list.
        if (preg_match('/^[-*]\s+/', $s)) {
            $flush(); $items = [];
            while ($i < $n && preg_match('/^[-*]\s+/', trim($lines[$i]))) { $items[] = preg_replace('/^[-*]\s+/', '', trim($lines[$i])); $i++; }
            $out[] = '<ul>' . implode('', array_map(fn($x) => '<li>' . help_inline($x) . '</li>', $items)) . '</ul>';
            continue;
        }
        // Ordered list.
        if (preg_match('/^\d+\.\s+/', $s)) {
            $flush(); $items = [];
            while ($i < $n && preg_match('/^\d+\.\s+/', trim($lines[$i]))) { $items[] = preg_replace('/^\d+\.\s+/', '', trim($lines[$i])); $i++; }
            $out[] = '<ol>' . implode('', array_map(fn($x) => '<li>' . help_inline($x) . '</li>', $items)) . '</ol>';
            continue;
        }
        $para[] = $s; $i++;
    }
    $flush();
    return implode("\n", $out);
}

function help_table_cells(string $row): array {
    $row = trim($row);
    $row = preg_replace('/^\|/', '', $row);
    $row = preg_replace('/\|$/', '', $row);
    return array_map('trim', explode('|', $row));
}

// Inline formatting on a single string. Escapes first, then applies code spans,
// links (with whitelisted targets), bold, and italic.
function help_inline(string $s): string {
    $s = htmlspecialchars($s, ENT_QUOTES);
    // Inline code (protect from further formatting).
    $s = preg_replace_callback('/`([^`]+)`/', fn($m) => '<code>' . $m[1] . '</code>', $s) ?? $s;
    // Images: ![alt](url)
    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) {
        $u = help_link_href($m[2], true);
        return $u ? '<img src="' . htmlspecialchars($u, ENT_QUOTES) . '" alt="' . $m[1] . '">' : $m[1];
    }, $s) ?? $s;
    // Links: [text](target)
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
        $u = help_link_href($m[2], false);
        return $u ? '<a href="' . htmlspecialchars($u, ENT_QUOTES) . '">' . $m[1] . '</a>' : $m[1];
    }, $s) ?? $s;
    $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $s) ?? $s;
    return $s;
}

// Resolve a Markdown link target to a safe href, or null to drop the link.
// Internal manual links (README.md, NN-name.md[#anchor]) map into /help.
function help_link_href(string $url, bool $image) {
    $url = trim($url);
    if ($url === 'README.md') return url('/help');
    if (preg_match('/^([0-9a-z-]+)\.md(#.*)?$/', $url, $m)) return url('/help/' . $m[1]);
    if (preg_match('~^https?://~i', $url)) return $url;
    if (str_starts_with($url, '#')) return $url;
    // Allow relative in-repo image paths only for images.
    if ($image && preg_match('~^[\w./-]+\.(png|jpe?g|gif|webp|svg)$~i', $url)) return $url;
    return null;
}
