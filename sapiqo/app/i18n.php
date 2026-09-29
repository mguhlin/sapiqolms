<?php
// Server-side UI internationalization. Mirrors the mglearn/contraband language
// set + switcher UX, but resolves the locale server-side so pages render fully
// translated (correct <html lang>/dir, no flash of English).
//
// Usage in views: echo e(t('nav.dashboard', 'Dashboard'));  (via <?= ... short echo)
// English is the inline default, so only non-English dictionaries are needed
// (app/lang/<code>.php returning a flat key => string array).

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const I18N_LANGS = [
    'en' => ['label' => 'English',    'dir' => 'ltr'],
    'es' => ['label' => 'Español',    'dir' => 'ltr'],
    'vi' => ['label' => 'Tiếng Việt', 'dir' => 'ltr'],
    'ar' => ['label' => 'العربية',     'dir' => 'rtl'],
    'hi' => ['label' => 'हिन्दी',       'dir' => 'ltr'],
    'ur' => ['label' => 'اردو',        'dir' => 'rtl'],
    'zh' => ['label' => '中文',        'dir' => 'ltr'],
];

const I18N_COOKIE = 'sapiqo_lang';

function i18n_normalize(?string $raw): string {
    if (!$raw) return '';
    $l = strtolower($raw);
    foreach (array_keys(I18N_LANGS) as $c) { if (str_starts_with($l, $c)) return $c; }
    return '';
}

// Locales that actually have a dictionary file present (plus English).
function available_locales(): array {
    static $out = null;
    if ($out !== null) return $out;
    $out = ['en'];
    foreach (glob(__DIR__ . '/lang/*.php') ?: [] as $f) {
        $c = basename($f, '.php');
        if (isset(I18N_LANGS[$c]) && $c !== 'en') $out[] = $c;
    }
    return $out;
}

// Resolve the active locale: ?lang= -> cookie -> configured default -> en.
function current_locale(): string {
    static $loc = null;
    if ($loc !== null) return $loc;
    $avail = available_locales();
    $cands = [
        $_GET['lang'] ?? null,
        $_COOKIE[I18N_COOKIE] ?? null,
        function_exists('setting') ? setting('default_locale', '') : '',
        lms_config()['default_locale'] ?? '',
    ];
    foreach ($cands as $c) {
        $n = i18n_normalize(is_string($c) ? $c : '');
        if ($n && in_array($n, $avail, true)) { $loc = $n; return $loc; }
    }
    $loc = 'en';
    return $loc;
}

function locale_dir(): string {
    return I18N_LANGS[current_locale()]['dir'] ?? 'ltr';
}

// The merged dictionary for the active locale (empty for English).
function i18n_dict(): array {
    static $d = null;
    if ($d !== null) return $d;
    $loc = current_locale();
    $d = [];
    if ($loc !== 'en') {
        $f = __DIR__ . '/lang/' . $loc . '.php';
        if (is_file($f)) { $arr = require $f; if (is_array($arr)) $d = $arr; }
    }
    return $d;
}

// Translate a key; $default is the English source text.
function t(string $key, ?string $default = null): string {
    $d = i18n_dict();
    if (isset($d[$key]) && $d[$key] !== '') return $d[$key];
    return $default ?? $key;
}

function set_locale_cookie(string $code): void {
    $n = i18n_normalize($code);
    if ($n === '' || !in_array($n, available_locales(), true)) return;
    setcookie(I18N_COOKIE, $n, [
        'expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    $_COOKIE[I18N_COOKIE] = $n;
}

// Render the language <select> switcher (only when >1 locale is available).
// The change handler is wired via a nonced <script> rather than an inline
// onchange attribute, because the site's CSP (script-src 'self' 'nonce-…',
// no 'unsafe-inline') blocks inline event-handler attributes — an inline
// onchange silently does nothing in the browser.
function language_switcher(string $class = 'lang-switcher'): string {
    static $seq = 0;
    $avail = available_locales();
    if (count($avail) < 2) return '';
    $cur = current_locale();
    $base = function_exists('url') ? url('/lang/') : '/lang/';
    $id = 'lang-switcher-' . (++$seq);
    $opts = '';
    foreach ($avail as $code) {
        $label = I18N_LANGS[$code]['label'] ?? $code;
        $sel = $code === $cur ? ' selected' : '';
        $opts .= '<option value="' . htmlspecialchars($base . $code) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
    $nonce = function_exists('csp_nonce') ? csp_nonce() : '';
    $jsId = json_encode($id);
    return '<select id="' . htmlspecialchars($id) . '" class="' . htmlspecialchars($class) . '" aria-label="Language / Idioma">' . $opts . '</select>'
        . '<script' . ($nonce !== '' ? ' nonce="' . htmlspecialchars($nonce) . '"' : '') . '>'
        . '(function(){var s=document.getElementById(' . $jsId . ');'
        . 'if(s)s.addEventListener("change",function(){if(this.value)location.href=this.value;});})();'
        . '</script>';
}
