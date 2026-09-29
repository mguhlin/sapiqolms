<?php
// Key/value settings store powering white-label branding. Values are edited in
// the admin Settings page and overlaid onto lms_config() for branding keys, so
// existing lms_config()['app_name'] etc. reflect admin changes with no code churn.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Branding settings that overlay config defaults (setting key => config key).
const BRANDING_KEYS = [
    'app_name'       => 'app_name',
    'app_tagline'    => 'app_tagline',
    'org_name'       => 'org_name',
    'catalog_name'   => 'catalog_name',
    'brand_mark'     => 'brand_mark',
    'theme_primary'  => 'theme_primary',
    'theme_accent'   => 'theme_accent',
    'logo'           => 'logo',
    'cert_signatory' => 'cert_signatory',
    'cert_signatory_title' => 'cert_signatory_title',
    'cert_provider'  => 'cert_provider',
    'cert_website'   => 'cert_website',
    // Not branding, but reuses the same DB-overlay mechanism so admins can
    // flip it live from Admin -> Settings instead of editing config.local.php.
    'allow_self_registration' => 'allow_self_registration',
    'session_idle_timeout_minutes' => 'session_idle_timeout_minutes',
];

function all_settings(bool $fresh = false): array {
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;
    $cache = [];
    try {
        foreach (db_all('SELECT `key`, value FROM settings') as $r) {
            $cache[$r['key']] = $r['value'];
        }
    } catch (Throwable $e) {
        // Table not migrated yet — behave as if empty.
    }
    return $cache;
}

function setting(string $key, string $default = ''): string {
    $s = all_settings();
    return isset($s[$key]) && $s[$key] !== '' ? $s[$key] : $default;
}

function set_setting(string $key, string $value): void {
    $now = now_utc();
    // Portable upsert.
    $exists = db_one('SELECT `key` FROM settings WHERE `key` = ?', [$key]);
    if ($exists) db_run('UPDATE settings SET value = ?, updated_at = ? WHERE `key` = ?', [$value, $now, $key]);
    else db_run('INSERT INTO settings (`key`, value, updated_at) VALUES (?,?,?)', [$key, $value, $now]);
    all_settings(true);   // refresh the in-process cache
}

// Overlay branding settings onto a built config array. Safe when the DB/table
// is unavailable (returns $config unchanged).
function branding_overlay(array $config): array {
    foreach (BRANDING_KEYS as $sKey => $cKey) {
        $v = setting($sKey, '');
        if ($v !== '') $config[$cKey] = $v;
    }
    return $config;
}

// --- Theme helpers -----------------------------------------------------------

// Lighten (+) or darken (-) a #rrggbb hex by a percentage.
function shade(string $hex, int $pct): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return '#' . $hex;
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    $adj = fn($c) => max(0, min(255, (int) round($c + ($pct / 100) * ($pct >= 0 ? (255 - $c) : $c))));
    return sprintf('#%02x%02x%02x', $adj($r), $adj($g), $adj($b));
}

// A <style> block overriding the navy/gold scales (+ font/radius) from brand
// settings. Returns '' when nothing custom is set (falls back to CSS defaults).
function brand_theme_style(): string {
    $primary = setting('theme_primary', '');
    $accent  = setting('theme_accent', '');
    $font    = setting('theme_font', '');
    $radius  = setting('theme_radius', '');
    if ($primary === '' && $accent === '' && $font === '' && $radius === '') return '';
    $css = ':root{';
    if ($primary !== '') {
        $css .= '--navy-900:' . shade($primary, -25) . ';--navy-800:' . shade($primary, -10) . ';'
              . '--navy-700:' . $primary . ';--navy-600:' . shade($primary, 12) . ';--navy-500:' . shade($primary, 24) . ';';
    }
    if ($accent !== '') {
        $css .= '--gold-500:' . $accent . ';--gold-400:' . shade($accent, 15) . ';--gold-300:' . shade($accent, 35) . ';';
    }
    if ($font !== '')   $css .= '--font:' . $font . ';';
    if ($radius !== '') { $r = (int) $radius; $css .= '--radius:' . $r . 'px;--radius-sm:' . max(0, $r - 5) . 'px;'; }
    $css .= '}';
    // Also apply the font to the body directly (some sheets set it explicitly).
    if ($font !== '') $css .= 'body{font-family:' . $font . '}';
    return '<style id="brand-theme">' . $css . '</style>';
}

// Curated theme presets — colors + font stack + corner radius that evoke the
// look & feel of familiar platforms (and a few originals), without their weight.
function theme_presets(): array {
    $sys = "'Segoe UI',system-ui,-apple-system,Roboto,Helvetica,Arial,sans-serif";
    $georgia = "Georgia,'Times New Roman',serif";
    $mont = "'Segoe UI',Verdana,Geneva,sans-serif";
    return [
        'sapiqo'     => ['label' => 'Sapiqo (default)', 'primary' => '#0b2e5b', 'accent' => '#f4b41a', 'font' => $sys, 'radius' => '14', 'note' => 'Navy & gold'],
        'wordpress'  => ['label' => 'WordPress',   'primary' => '#21759b', 'accent' => '#d54e21', 'font' => $sys, 'radius' => '4',  'note' => 'Classic blue & orange, flat'],
        'joomla'     => ['label' => 'Joomla',      'primary' => '#1a3867', 'accent' => '#f9a21b', 'font' => $sys, 'radius' => '6',  'note' => 'Atum blue & amber'],
        'moodle'     => ['label' => 'Moodle',      'primary' => '#0f6cbf', 'accent' => '#f98012', 'font' => $sys, 'radius' => '12', 'note' => 'Boost blue & orange, rounded'],
        'canvas'     => ['label' => 'Canvas',      'primary' => '#0374b5', 'accent' => '#f2960d', 'font' => $sys, 'radius' => '6',  'note' => 'Instructure blue'],
        'blackboard' => ['label' => 'Blackboard',  'primary' => '#262626', 'accent' => '#ffcb05', 'font' => $sys, 'radius' => '4',  'note' => 'Charcoal & yellow'],
        'schoology'  => ['label' => 'Schoology',   'primary' => '#4a7fb5', 'accent' => '#f4a01c', 'font' => $sys, 'radius' => '8',  'note' => 'Soft blue'],
        'forest'     => ['label' => 'Forest',      'primary' => '#1f5c3d', 'accent' => '#e0a92e', 'font' => $sys, 'radius' => '12', 'note' => 'Green & wheat'],
        'slate'      => ['label' => 'Slate',       'primary' => '#334155', 'accent' => '#38bdf8', 'font' => $sys, 'radius' => '10', 'note' => 'Cool gray & sky'],
        'rose'       => ['label' => 'Rose',        'primary' => '#9d2449', 'accent' => '#e8a33d', 'font' => $georgia, 'radius' => '12', 'note' => 'Crimson & gold, serif'],
        'grape'      => ['label' => 'Grape',       'primary' => '#4c2a86', 'accent' => '#21c0a4', 'font' => $mont, 'radius' => '16', 'note' => 'Purple & teal, pill'],
        'contrast'   => ['label' => 'High contrast', 'primary' => '#000000', 'accent' => '#ffd400', 'font' => $sys, 'radius' => '6', 'note' => 'Max legibility'],
    ];
}

// Resolved brand color pair for non-CSS consumers (badge generator, etc.).
function brand_colors(): array {
    return [
        'primary' => setting('theme_primary', '#0b2e5b'),   // default navy-800
        'accent'  => setting('theme_accent', '#f4b41a'),    // default gold-500
    ];
}

function hex_to_rgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return [11, 46, 91];
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
