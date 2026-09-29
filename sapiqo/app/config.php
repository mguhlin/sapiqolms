<?php
// Sapiqo configuration.
// Defaults are safe for local/offline use with SQLite. For production, copy
// app/config.local.example.php to app/config.local.php and edit it (that file
// is git-ignored and overrides the values below).

declare(strict_types=1);

function lms_config(): array {
    static $config = null;
    if ($config !== null) return $config;

    $root = dirname(__DIR__);   // the sapiqo/ code folder (replaceable on upgrade)

    // Persistent data + content live OUTSIDE the replaceable code so upgrading is
    // "replace the sapiqo/ folder." Resolution order for the data root:
    //   1) SAPIQO_DATA env var, 2) sibling ../sapiqo-data, 3) internal ./data.
    $dataRoot = getenv('SAPIQO_DATA') ?: null;
    if (!$dataRoot) {
        foreach (['/sapiqo-data'] as $name) {
            if (is_dir(dirname($root) . $name)) { $dataRoot = dirname($root) . $name; break; }
        }
        if (!$dataRoot) $dataRoot = $root;
    }

    $defaults = [
        'app_name'    => 'Sapiqo',
        'public_url' => '', // production canonical URL, including subdirectory if any
        'trusted_proxies' => [], // exact reverse-proxy socket IP addresses

        'app_tagline' => 'Learning made clear.',
        'org_name'    => '',  // set in Admin → Settings
        // A short label identifying this specific edition/flavor of the code
        // (e.g. two installs can share branding but run genuinely different
        // code, like a Vimeo-video edition vs. a self-hosted-video edition).
        // Only used to warn before applying a software-update package built
        // from a different edition (see app/updater.php). Leave '' to skip
        // the check entirely (e.g. a generic template with nothing to compare).
        'edition_label' => '',
        'help_contact_url' => '',  // support/contact form or mailto: link, shown in Help + the Terms/Privacy page
        'org_url'          => '',  // operator's main website, shown in the footer if set
        'org_blog_url'     => '',  // operator's blog, shown in the footer if set
        // White-label branding (overridable in Admin â Settings).
        'catalog_name'   => '',    // course-site brand; defaults to app_name . ' Courses'
        'brand_mark'     => '',    // 1-2 char logo mark; defaults to first letter of app_name
        'theme_primary'  => '',    // brand primary (navy) hex; '' = CSS default
        'theme_accent'   => '',    // brand accent (gold) hex;  '' = CSS default
        'logo'           => '',    // uploaded logo filename (in data/branding/)
        'cert_signatory' => '',        // signatory name (e.g., Ben Starr)
        'cert_signatory_title' => '',  // signatory title (e.g., Executive Director)
        'cert_provider' => '',         // CPE provider line (e.g., TEA Provider #500114)
        'cert_website' => '',          // footer website (e.g., www.example.org)
        'default_locale' => 'en',  // default UI language (ISO code)

        // Database: 'sqlite' (zero-config) or 'mysql' (production).
        'db_driver'   => 'sqlite',
        'sqlite_path' => $dataRoot . '/data/lms.sqlite',
        'mysql' => [
            'host'    => '127.0.0.1',
            'port'    => 3306,
            'dbname'  => 'sapiqo',
            'user'    => 'sapiqo',
            'pass'    => '',
            'charset' => 'utf8mb4',
        ],

        // Drop-in course content: each subfolder with a course.json is auto-registered.
        // Courses live in a sibling "content/" folder (kept out of the code + data
        // folders for a clean layout). Override with SAPIQO_COURSES if yours differs.
        'courses_dir' => getenv('SAPIQO_COURSES')
            ?: (is_dir(dirname($root) . '/content') ? dirname($root) . '/content' : dirname($root)),

        // Writable persistent directories.
        'data_dir'    => $dataRoot . '/data',
        'badge_dir'   => $dataRoot . '/data/badges',
        'upload_dir'  => $dataRoot . '/data/uploads',   // editor upload staging
        'export_dir'  => $dataRoot . '/data/exports',   // split-export staging
        'max_upload_mb' => 200,                          // per-file cap (video)
        'export_chunk_mb' => 0,                          // 0 = auto (fit under upload limit)

        // Optional shared badge library (source art) for name-matching.
        'badge_library' => $dataRoot . '/badge-library',

        // Placeholder badge artwork (persistent).
        'badge_placeholder' => $dataRoot . '/data/badge-placeholder.png',

        // Session cookie name.
        'session_name' => 'sapiqo',

        // Show PHP errors in the response (development only). Leave false in
        // production so stack traces are logged, never sent to the browser.
        'debug' => false,

        // Allow public self-registration (admins can always create accounts).
        'allow_self_registration' => true,

        // Sign a user out after this many minutes of inactivity (0 = never,
        // sessions last until the browser closes). Live-editable from
        // Admin -> Settings -> Accounts; see session_idle_timeout_check() in
        // app/auth.php.
        'session_idle_timeout_minutes' => 60,

        // Single sign-on. Disabled until a provider is configured with real
        // client credentials. Each provider becomes a button on the login page.
        'sso' => [
            'google' => [
                'enabled'       => false,
                'client_id'     => '',
                'client_secret' => '',
                // Redirect URI must be registered with the provider:
                //   {base_url}/auth/google/callback
            ],
            'microsoft' => [
                'enabled'       => false,
                'client_id'     => '',
                'client_secret' => '',
                'tenant'        => 'common',
                //   {base_url}/auth/microsoft/callback
            ],
        ],
    ];

    // Local overrides live in the persistent data root (preferred) so they
    // survive upgrades; fall back to app/config.local.php for older layouts.
    $local = $dataRoot . '/config.local.php';
    if (!is_file($local)) $local = $root . '/app/config.local.php';
    $overrides = is_file($local) ? (require $local) : [];

    // Shallow merge with one level of nesting for mysql/sso sub-arrays.
    $merged = $defaults;
    foreach ($overrides as $k => $v) {
        if (is_array($v) && isset($defaults[$k]) && is_array($defaults[$k])) {
            $merged[$k] = array_replace_recursive($defaults[$k], $v);
        } else {
            $merged[$k] = $v;
        }
    }
    // Cache the file-based config FIRST so re-entrant lms_config() calls made
    // while reading DB settings return safely (prevents recursion).
    $config = $merged;
    // Overlay white-label branding settings from the DB when available.
    if (function_exists('branding_overlay')) {
        try { $config = branding_overlay($merged); } catch (Throwable $e) { $config = $merged; }
    }
    // Derived defaults for empty branding fields.
    if (($config['catalog_name'] ?? '') === '') $config['catalog_name'] = $config['app_name'] . ' Courses';
    if (($config['brand_mark'] ?? '') === '') $config['brand_mark'] = mb_substr($config['app_name'], 0, 1);
    return $config;
}
