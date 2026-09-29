<?php
// Small helpers shared across the app. No framework, no external dependencies.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Only allow http(s) URLs into href/src attributes. Blocks javascript:/vbscript:/
// data: URI XSS regardless of quoting style (unlike a quote-anchored regex, this
// checks the whole trimmed value). Returns '' for anything else so callers can
// skip rendering a link/embed rather than emit an unsafe one. Use for values
// that are always meant to be an absolute external URL (e.g. a video/embed URL).
function safe_url(string $u): string {
    $u = trim($u);
    return preg_match('#^https?://#i', $u) ? $u : '';
}

// Like safe_url(), but also allows a scheme-less relative reference (e.g. the
// course-relative "media/photo.jpg" paths produced by uploads) — only an
// explicit non-http(s) scheme (javascript:, data:, vbscript:, file:, …) is
// rejected. Use for src/href values that may point at either a local upload
// or an external link.
function safe_media_ref(string $u): string {
    $u = trim($u);
    if ($u === '' || preg_match('/[\x00-\x20\x7f]/', $u)) return '';
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $u)) {   // has an explicit URI scheme
        return preg_match('#^https?://#i', $u) ? $u : '';
    }
    return $u;   // no scheme = relative reference, safe
}

// Terms of Service & Privacy Policy content (public page + footer link). Looks
// for an operator-supplied Markdown file at <data_dir>/legal/terms-privacy.md
// (outside the code folder, so it's update-safe and per-instance — same
// pattern as config.local.php and the badge library). Falls back to a generic,
// config-driven default so the page is never empty on a fresh install.
//
// An admin who edits this page from Admin -> Terms & Privacy (the built-in
// rich-text editor) produces a sibling terms-privacy.html file, which — if
// present — takes precedence over the Markdown source. That HTML is already
// sanitized at save time (editor_sanitize_html()), so it's safe to emit as-is.
// A translated policy can be dropped in per locale as terms-privacy.<loc>.html
// (sanitized HTML, e.g. from the editor) or terms-privacy.<loc>.md, which take
// precedence for that locale; everything falls back to the English files, then
// to the shipped default. Resolution mirrors help_render(): locale HTML ->
// locale MD -> English HTML -> English MD -> shipped default.
function legal_content_html(): string {
    $cfg = lms_config();
    $dir = rtrim($cfg['data_dir'] ?? '', '/') . '/legal';
    $loc = function_exists('current_locale') ? current_locale() : 'en';
    if ($loc !== 'en') {
        $locHtml = $dir . '/terms-privacy.' . $loc . '.html';
        if (is_file($locHtml)) return (string) file_get_contents($locHtml);
        $locMd = $dir . '/terms-privacy.' . $loc . '.md';
        if (is_file($locMd)) {
            $md = (string) file_get_contents($locMd);
            return function_exists('cr_md_to_html') ? cr_md_to_html($md) : nl2br(e($md));
        }
    }
    $htmlFile = $dir . '/terms-privacy.html';
    if (is_file($htmlFile)) return (string) file_get_contents($htmlFile);
    $file = $dir . '/terms-privacy.md';
    $md = is_file($file) ? (string) file_get_contents($file) : legal_default_markdown();
    return function_exists('cr_md_to_html') ? cr_md_to_html($md) : nl2br(e($md));
}

function legal_default_markdown(): string {
    $cfg = lms_config();
    $org = $cfg['org_name'] ?: $cfg['app_name'];
    $contact = $cfg['help_contact_url'] ?? '';
    $contactLine = $contact !== ''
        ? "Questions or requests about your data: [contact us]($contact)."
        : "Questions or requests about your data: contact your site administrator.";
    return <<<MD
*This is the default policy shipped with the software. **{$org}** should replace*
*it with a policy describing its actual practices before real users are enrolled —*
*see `<data>/legal/terms-privacy.md`.*

### Terms of Service

**{$org}** operates this learning platform ("the Platform"). By creating an
account or being enrolled by an administrator, you agree to use it only for
its intended educational purpose, keep your login credentials confidential,
and not attempt to access data, roles, or courses you have not been granted.
Course content belongs to {$org} or its content providers; content you
personally submit (forum posts, uploaded avatar) remains yours. The Platform
is provided as-is, without warranty of uninterrupted availability.

### Privacy Policy — what we collect

Account details you or your administrator provide (name, email, campus,
organization), your course enrollment and progress, quiz and assignment
results, forum posts, and the badges/certificates you earn. We practice data
minimization — only the fields needed to run courses, track completion, and
issue credentials are collected. No advertising, analytics, or third-party
tracking is built into this software.

### How it is used

To authenticate you, show your courses and progress, issue badges and
certificates, and let your organization's designated managers review
completion for the groups they manage. Data is not sold, rented, or shared
with third parties for marketing, and is not used to train AI models.

### Data retention

Badges, certificates, and your completion record are kept as a permanent
record even if a course is later removed or your enrollment window expires.
Account data is retained while your account is active.

### Security

Passwords are stored only as salted one-way hashes; sessions use HttpOnly,
SameSite cookies (Secure over HTTPS); all state-changing requests are
CSRF-protected; database access uses parameterized queries; an administrative
audit log records sensitive actions.

### Student data (FERPA and Texas)

Where this Platform is used with students, records may constitute education
records under FERPA and should be handled consistent with your state's
student-data-privacy requirements. Because data is hosted on
organization-controlled infrastructure, access, retention, and deletion are
governed by {$org}.

### International participants & GDPR

This software is self-hosted — **{$org}** operates its own copy on
infrastructure it controls, and is the data controller for any personal
information its instance collects; the software's authors never receive or
process that data. If {$org} has participants in the European Economic Area,
the UK, or another jurisdiction with its own data-protection law, {$org}
should confirm and document: its lawful basis for processing (typically
contract performance or legitimate interests), where its server is hosted
and what safeguards apply to any resulting international transfer (e.g.
Standard Contractual Clauses, if the server is outside the participant's
region), and how it will handle access/correction/erasure/portability
requests from those participants — the same request channel as "Your rights"
below can be used, or {$org} may set up a dedicated one.

### Your rights

You (or your organization's administrator on your behalf) may request to
access, correct, export, or delete your data. {$contactLine}

### Changes

Material changes to this policy will be posted here with an updated date.
MD;
}

// Base URL path the app is mounted at (handles subdirectory installs).
function base_path(): string {
    // The front controller is public/index.php; the web root is public/.
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = rtrim(str_replace('/index.php', '', $script), '/');
    return $dir; // '' when at web root
}

function url(string $path = ''): string {
    $path = '/' . ltrim($path, '/');
    return base_path() . $path;
}

// Render a page header. If a hero image exists at
// public/assets/img/heroes/<name>.webp it becomes a banner with the title
// overlaid; otherwise a plain heading. Drop generated heroes in to light them up.
function hero_banner(string $name, string $title, string $subtitle = ''): string {
    $name = preg_match('/^[a-z0-9-]+$/', $name) ? $name : 'page';
    $rel = "/assets/img/heroes/$name.webp";
    $file = dirname(__DIR__) . '/public' . $rel;
    $h = '<h1>' . e($title) . '</h1>' . ($subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '');
    if (!is_file($file)) return '<div class="page-head">' . $h . '</div>';
    $style = "background-image:linear-gradient(90deg,rgba(7,27,56,.92),rgba(7,27,56,.55) 55%,rgba(7,27,56,.12)),url('" . e(url($rel)) . "')";
    return '<div class="page-hero" style="' . $style . '"><div class="page-hero__in">' . $h . '</div></div>';
}

// Inline UI icon from /assets/img/icons/<name>.svg. Returns '' if the file is
// absent so callers can fall back (e.g. to an emoji). Decorative (aria-hidden).
function ui_icon(string $name, int $px = 20): string {
    if (!preg_match('/^[a-z0-9-]+$/', $name)) return '';
    $rel = "/assets/img/icons/$name.svg";
    if (!is_file(dirname(__DIR__) . '/public' . $rel)) return '';
    return '<img class="ui-ic" src="' . e(url($rel)) . '" width="' . $px . '" height="' . $px
         . '" alt="" aria-hidden="true">';
}

// Friendly "nothing here yet" block with an illustration from /assets/img/empty/.
// $title is escaped; $msgHtml is trusted caller markup (may contain links).
function empty_state(string $img, string $title, string $msgHtml = ''): string {
    $img = preg_match('/^[a-z0-9-]+$/', $img) ? $img : 'empty-courses';
    $rel = "/assets/img/empty/$img.png";
    $pic = is_file(dirname(__DIR__) . '/public' . $rel)
        ? '<img src="' . e(url($rel)) . '" alt="" class="empty-state__img" loading="lazy">' : '';
    return '<div class="empty-state">' . $pic
         . '<p class="empty-state__title">' . e($title) . '</p>'
         . ($msgHtml !== '' ? '<p class="muted">' . $msgHtml . '</p>' : '')
         . '</div>';
}

// Admin areas as separate sections (used by the /admin hub, the section pages,
// and the Admin nav dropdown). Each card: [href, title, desc, icon]; href
// '@rescan' is the special rescan POST button.
function admin_sections(): array {
    return [
        'courses' => ['label' => 'Course management', 'icon' => '📚', 'cards' => [
            ['/admin/courses', 'Create a course', 'Build a course visually — drag-and-drop pages, media, embeds, and quizzes — or author in Markdown.', '➕'],
            ['/admin/courses', 'Manage & edit courses', 'Open any course in the ✏ visual block editor; hide, clone, or configure prerequisites, CPE, expiry, sequential, and forum.', '✏️'],
            ['/admin/certifications', 'Certifications', 'Manage certification programs on their own — publish/draft, edit, CPE hours, and export (kept separate from regular courses).', '🎓'],
            ['@rescan', 'Rescan courses', 'Detect course folders added to or removed from the content/ folder.', '🔄'],
            ['/admin/reports', 'Training reports', 'Enrollment, completion-rate, and 8-week activity dashboards.', '📊'],
            ['/admin/gradebook', 'Gradebook', 'Custom assessments in a scores grid (points, categories, letter grades) plus quiz results.', '🧮'],
            ['/admin/completions', 'View completions', 'Every enrollment — status, dates, and badge code.', '✅'],
        ]],
        'accounts' => ['label' => 'Account management', 'icon' => '👥', 'cards' => [
            ['/admin/accounts', 'Roles & access', 'Grant admins, course developers, and group managers with least-privilege permissions.', '🔑'],
            ['/admin/users', 'Manage users', 'Search accounts; edit profile, role, and enrollments; run bulk actions.', '👤'],
            ['/admin/orgs', 'Organizations', 'Districts/clients (e.g. Aldirk ISD): enroll members, subscribe a whole org or its groups to courses, and assign org managers.', '🏢'],
            ['/admin/groups', 'Groups', 'Organize learners (e.g. by campus or cohort), subscribe them to courses, and assign sub-admins.', '🏫'],
            ['/admin/codes', 'Enrollment codes', 'Generate (or enter external) codes that auto-enroll learners into one or many courses; see issued codes, redemptions, and affected courses.', '🎟️'],
            ['/admin/audit', 'Audit log', 'Who did what — logins, edits, imports, and backups.', '📜'],
        ]],
        'imports' => ['label' => 'Imports & backups', 'icon' => '📥', 'cards' => [
            ['/admin/import', 'Imports (users & courses)', 'Bring in users (CSV or OneRoster SIS) and courses (package, Common Cartridge, or SCORM).', '📥'],
            ['/admin/template.csv', 'Download CSV template', 'The exact column layout for the bulk user import.', '📄'],
            ['/admin/backup', 'Download backup', 'A single snapshot archive of ALL data for disaster recovery or moving servers.', '💾'],
        ]],
        'exports' => ['label' => 'Exports', 'icon' => '📤', 'cards' => [
            ['/admin/users.csv', 'User roster (CSV)', 'Every account with profile fields — the full roster.', '👥'],
            ['/admin/completions?export=1', 'Completions (CSV)', 'Enrollments, status, dates, and badge codes — who finished what.', '✅'],
            ['/admin/gradebook', 'Gradebook & grades', 'Open a course scores grid; each has its own CSV export.', '🧮'],
            ['/admin/courses', 'Course content', 'Export any course to another LMS (Common Cartridge), a .tar package, or .json — from each course’s ⋯ menu.', '📦'],
            ['/admin/audit?export=1', 'Audit log (CSV)', 'A record of admin actions.', '📜'],
            ['/admin/backup', 'Full data backup', 'Everything as a single archive.', '💾'],
        ]],
        'settings' => ['label' => 'Settings & integrations', 'icon' => '⚙️', 'cards' => [
            ['/admin/settings', 'Branding & settings', 'Platform name, logo, theme skins, certificate signatory & CPE provider, default language, forum reminders.', '🎨'],
            ['/admin/mail', 'Mail (SMTP)', 'Check whether outgoing email is configured, and send a test message — welcome, password-reset, and reminder emails use this.', '✉️'],
            ['/admin/share', 'Share on your network', 'Running a local demo? Show the Wi-Fi/LAN address so staff on the same network can open the site and try it out.', '📡'],
            ['/admin/api-keys', 'API keys', 'Tokens for the REST API.', '🔌'],
            ['/admin/lti', 'LTI 1.3', 'Launch Sapiqo as a tool from Canvas, Moodle, or Blackboard, with grade passback.', '🔗'],
            ['/admin/update', 'Software updates', 'Update the LMS code from an uploaded package; backup & rollback included.', '⬆️'],
        ]],
    ];
}

// Private-LAN IPv4 address(es) of this host — for sharing a local demo over
// Wi-Fi so other machines on the same network can connect.
function local_network_ips(): array {
    $ips = [];
    if (function_exists('net_get_interfaces')) {
        foreach (net_get_interfaces() ?: [] as $if) {
            foreach ($if['unicast'] ?? [] as $u) {
                $ip = (string) ($u['address'] ?? '');
                if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip)) $ips[$ip] = true;
            }
        }
    }
    if (!$ips) {                      // fallback: the primary outbound-interface IP
        $s = @stream_socket_client('udp://8.8.8.8:53', $e, $es, 1);
        if ($s) {
            $n = @stream_socket_get_name($s, false);
            if ($n) { $ip = explode(':', $n)[0]; if ($ip) $ips[$ip] = true; }
            fclose($s);
        }
    }
    return array_keys($ips);
}

// Per-request CSP nonce for inline <script> blocks (lets us drop the weak
// 'unsafe-inline' from script-src). Same value throughout one request.
function csp_nonce(): string {
    static $n = null;
    if ($n === null) $n = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    return $n;
}

// --- SSRF protection ---------------------------------------------------------
// True only if $url is an http(s) URL whose host resolves entirely to public IPs
// (blocks localhost, private ranges, and cloud metadata like 169.254.169.254).
// Resolve once and pin cURL to the checked addresses to prevent DNS rebinding.
function public_url_addresses(string $url): array {
    $u = parse_url(trim($url));
    if (!$u || !in_array(strtolower($u['scheme'] ?? ''), ['http', 'https'], true)
        || empty($u['host']) || isset($u['user']) || isset($u['pass'])) return [];
    $host = trim($u['host'], '[]');
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
    else {
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            if (!empty($r['ip'])) $ips[] = $r['ip'];
            if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
        }
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            || str_starts_with(strtolower($ip), '::ffff:')) return [];
    }
    return array_values(array_unique($ips));
}
function url_is_safe_public(string $url): bool { return public_url_addresses($url) !== []; }
function safe_fetch(string $url, int $timeoutSec = 12, int $maxBytes = 20000000): ?string {
    $ips = public_url_addresses($url);
    // Fail closed if cURL is absent; streams cannot pin TLS hostname + address.
    if (!$ips || !function_exists('curl_init') || $maxBytes <= 0) return null;
    $u = parse_url($url); $host = $u['host'];
    $port = (int) ($u['port'] ?? (strtolower($u['scheme']) === 'https' ? 443 : 80));
    $data = ''; $ch = curl_init($url);
    $opts = [
        CURLOPT_TIMEOUT => $timeoutSec, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROXY => '', CURLOPT_USERAGENT => 'Sapiqo',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$data, $maxBytes): int {
            if (strlen($data) + strlen($chunk) > $maxBytes) return 0;
            $data .= $chunk; return strlen($chunk);
        },
    ];
    if (!filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
        $ip = str_contains($ips[0], ':') ? '[' . $ips[0] . ']' : $ips[0];
        $opts[CURLOPT_RESOLVE] = ["$host:$port:$ip"];
    }
    curl_setopt_array($ch, $opts);
    $ok = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return $ok !== false && $code >= 200 && $code < 300 && $data !== '' ? $data : null;
}

// Guard against Zip/Tar Slip: reject an archive whose entries would escape the
// extraction directory (absolute paths or ".." segments). Also guards against
// zip bombs — a small compressed file that decompresses to exhaust disk space
// or an absurd entry count — by capping total uncompressed size and entry
// count before extraction is ever attempted. Returns true if safe.
// Validate raw archive names before library normalization can conceal traversal.
function archive_path_safe(string $name): bool {
    $name = str_replace('\\', '/', $name);
    return $name !== '' && !preg_match('/[\x00-\x1f\x7f]/', $name)
        && $name[0] !== '/' && !preg_match('#(^|/)\.\.(/|$)|^[A-Za-z]:#', $name);
}
function archive_entries_safe(string $archivePath): bool {
    $maxEntries = 20000; $maxBytes = 2 * 1024 * 1024 * 1024;
    $fh = @fopen($archivePath, 'rb');
    if (!$fh) return false;
    try {
        $magic = fread($fh, 4); rewind($fh);
        if (str_starts_with($magic, "PK")) {
            // Read the ZIP central directory; works even without ext-zip.
            $size = filesize($archivePath);
            $tailSize = min($size, 65557);
            fseek($fh, $size - $tailSize); $tail = fread($fh, $tailSize);
            $pos = strrpos($tail, "PK\x05\x06");
            if ($pos === false || strlen($tail) - $pos < 22) return false;
            $e = unpack('vdisk/vcdDisk/vdiskEntries/ventries/VcdSize/Voffset/vcomment', substr($tail, $pos + 4, 18));
            // ZIP64 and multi-disk archives are intentionally unsupported.
            if ($e['disk'] || $e['cdDisk'] || $e['entries'] > $maxEntries || $e['entries'] === 65535
                || $e['entries'] !== $e['diskEntries'] || $e['offset'] + $e['cdSize'] > $size
                || $pos + 22 + $e['comment'] !== strlen($tail)) return false;
            fseek($fh, $e['offset']); $total = 0;
            for ($i = 0; $i < $e['entries']; $i++) {
                $h = fread($fh, 46);
                if (strlen($h) !== 46 || substr($h, 0, 4) !== "PK\x01\x02") return false;
                $v = unpack('vflags', substr($h, 8, 2));
                $u = unpack('Vsize', substr($h, 24, 4))['size'];
                $n = unpack('vname/vextra/vcomment', substr($h, 28, 6));
                $attrs = unpack('Vattrs', substr($h, 38, 4))['attrs'];
                $name = fread($fh, $n['name']);
                $mode = ($attrs >> 16) & 0170000;
                if (!archive_path_safe($name) || ($v['flags'] & 1) || $u === 0xffffffff
                    || ($mode && !in_array($mode, [0100000, 0040000], true))) return false;
                $total += $u; if ($total > $maxBytes) return false;
                if (fseek($fh, $n['extra'] + $n['comment'], SEEK_CUR) !== 0) return false;
            }
            // Let the extractor also validate central/local header consistency.
            $p = new PharData($archivePath);
            return count($p) > 0;
        }
        // TAR / gzip TAR: stream headers and payload, bounded by expanded size.
        fclose($fh);
        $fh = @fopen(str_starts_with($magic, "\x1f\x8b") ? 'compress.zlib://' . $archivePath : $archivePath, 'rb');
        if (!$fh) return false;
        $entries = 0; $total = 0;
        while (!feof($fh)) {
            $h = fread($fh, 512);
            if ($h === str_repeat("\0", 512)) return $entries > 0;
            if (strlen($h) !== 512 || ++$entries > $maxEntries) return false;
            $name = rtrim(substr($h, 0, 100), "\0");
            $prefix = rtrim(substr($h, 345, 155), "\0");
            if ($prefix !== '') $name = $prefix . '/' . $name;
            $type = $h[156];
            // Reject links/devices and PAX/GNU extended headers (hidden paths).
            if (!in_array($type, ["\0", '0', '5'], true) || !archive_path_safe($name)) return false;
            $rawSize = trim(substr($h, 124, 12), "\0 ");
            if ($rawSize !== '' && !preg_match('/^[0-7]+$/', $rawSize)) return false;
            $bytes = octdec($rawSize ?: '0'); $total += $bytes;
            if ($total > $maxBytes) return false;
            $remaining = (int) (ceil($bytes / 512) * 512);
            while ($remaining > 0) {
                $buf = fread($fh, min(1048576, $remaining));
                if ($buf === false || $buf === '') return false;
                $remaining -= strlen($buf);
            }
        }
        return false;
    } catch (Throwable $e) {
        return false;
    } finally {
        if (is_resource($fh)) fclose($fh);
    }
}

function request_is_https(): bool {
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
    $trusted = lms_config()['trusted_proxies'] ?? [];
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted, true)
        && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}
function base_url_absolute(): string {
    $canonical = rtrim((string) (lms_config()['public_url'] ?? ''), '/');
    if ($canonical !== '') return $canonical;
    // SERVER_NAME is server configuration; never build reset/SSO links from Host.
    $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    if (!preg_match('/^(?:[a-zA-Z0-9.-]+|\[[a-fA-F0-9:]+\])$/', $host)) $host = 'localhost';
    $https = request_is_https(); $port = (int) ($_SERVER['SERVER_PORT'] ?? ($https ? 443 : 80));
    $suffix = in_array($port, [80,443], true) ? '' : ':' . $port;
    return ($https ? 'https' : 'http') . '://' . $host . $suffix . base_path();
}

function redirect(string $path): never {
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function json_out($data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function input(string $key, $default = ''): string {
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : (string) $default;
}

function want_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return str_contains($accept, 'application/json') || $xrw === 'XMLHttpRequest'
        || str_starts_with(trim($_SERVER['REQUEST_URI'] ?? ''), url('/api/'));
}

// Flash messages ---------------------------------------------------------------

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function take_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// CSRF ------------------------------------------------------------------------

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || $sent === '' || empty($_SESSION['csrf'])
        || !hash_equals($_SESSION['csrf'], $sent)) {
        if (want_json()) json_out(['error' => 'Invalid CSRF token'], 419);
        http_response_code(419);
        exit('Invalid or expired form token. Go back and try again.');
    }
}

function valid_email(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Renders a PHP view within the shared layout.
function view(string $name, array $vars = [], ?string $title = null): void {
    $vars['__title'] = $title ?? lms_config()['app_name'];
    extract($vars, EXTR_SKIP);
    $viewFile = dirname(__DIR__) . '/app/views/' . $name . '.php';
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require dirname(__DIR__) . '/app/views/layout.php';
}
