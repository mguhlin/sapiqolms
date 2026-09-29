<?php
// Copy this file to app/config.local.php and edit for your server.
// Anything you set here overrides app/config.php. This file is git-ignored.

return [
    'public_url' => 'https://lms.example.org', // canonical address for reset links, SSO, and LTI

    // --- Production database (MySQL / MariaDB) ---
    'db_driver' => 'mysql',
    'mysql' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'dbname' => 'sapiqo',
        'user'   => 'sapiqo',
        'pass'   => 'CHANGE_ME',
    ],

    // For a quick local/offline install instead, comment out the block above and use:
    // 'db_driver' => 'sqlite',

    // Set to false to require admins to create all accounts (no public sign-up).
    'allow_self_registration' => true,

    // --- Login throttling (defaults shown; tune per your risk posture) ---
    'login_max_per_account' => 5,    // failures per email before lockout
    'login_max_per_ip'      => 15,   // failures per IP before lockout
    'login_window_seconds'  => 900,  // sliding window (15 minutes)

    // If behind a reverse proxy/load balancer, list its IPs so X-Forwarded-For
    // is trusted for client IP + throttling. Leave empty for direct connections.
    'trusted_proxies' => [],

    // --- Optional email (password resets, welcome + completion messages) ---
    // Disabled by default; the LMS runs fully offline without it. When off,
    // password-reset links are generated in the admin user editor to share by hand.
    // 'from'/'from_name' can be whatever you like regardless of which SMTP
    // account you authenticate as below (see the Gmail note on 'user').
    // Check Admin -> Mail in the app for a status page + a "send test email"
    // button that reports exactly which step failed if something's misconfigured.
    'mail' => [
        'enabled'   => false,
        'transport' => 'smtp',                 // 'smtp' or 'mail' (PHP mail())
        'from'      => 'lms@yourdistrict.org',
        'from_name' => 'District LMS',
        'smtp' => [
            // A generic SMTP relay (shown here), OR Gmail/Google Workspace —
            // the same idea as WP Mail SMTP / FluentSMTP's "Gmail" option in
            // WordPress: set host => 'smtp.gmail.com', port => 587,
            // secure => 'tls', and see 'user'/'pass' below.
            'host'   => 'smtp.yourdistrict.org',
            'port'   => 587,
            // The account to log in as. For Gmail, this is the Gmail/Workspace
            // address itself. If 'from' above is a DIFFERENT address than this
            // one, it must be a verified "Send mail as" alias in that Gmail
            // account's Settings -> Accounts and Import — otherwise Gmail
            // rejects the send or silently substitutes its own address.
            'user'   => '',
            // For Gmail: an APP PASSWORD, not the normal login password — turn
            // on 2-Step Verification for that account, then generate one at
            // https://myaccount.google.com/apppasswords
            'pass'   => '',
            'secure' => 'tls',                 // 'tls' | 'ssl' | '' (none)
        ],
    ],

    // --- Optional single sign-on ---
    // Register the redirect URI {your-site}/auth/google/callback (or /microsoft/)
    // with the provider, then fill in the credentials and set enabled => true.
    'sso' => [
        'google' => [
            'enabled'       => false,
            'client_id'     => '',
            'client_secret' => '',
        ],
        'microsoft' => [
            'enabled'       => false,
            'client_id'     => '',
            'client_secret' => '',
            'tenant'        => 'common',
        ],
        // Clever (K-12 SSO/rostering). In the Clever dashboard set the redirect
        // URI to {your-site}/auth/clever/callback. Districts on Skyward/Ascender
        // can often federate their SIS through Clever.
        'clever' => [
            'enabled'       => false,
            'client_id'     => '',
            'client_secret' => '',
        ],
        // ClassLink LaunchPad (K-12 SSO; common in Texas). Redirect URI:
        // {your-site}/auth/classlink/callback. Skyward/Ascender districts can
        // federate their SIS through ClassLink.
        'classlink' => [
            'enabled'       => false,
            'client_id'     => '',
            'client_secret' => '',
        ],
        // Rhythm (K-12 SSO). Endpoints vary by district tenant, so set them
        // explicitly. Redirect URI: {your-site}/auth/rhythm/callback.
        'rhythm' => [
            'enabled'       => false,
            'client_id'     => '',
            'client_secret' => '',
            'auth_url'      => '',   // e.g. https://<tenant>.rhithm.app/oauth/authorize
            'token_url'     => '',   // e.g. https://<tenant>.rhithm.app/oauth/token
            'userinfo_url'  => '',   // e.g. https://<tenant>.rhithm.app/oauth/userinfo
            'scope'         => 'openid email profile',
            // Optional: map non-standard userinfo field names, e.g.
            // 'map' => ['email' => 'email', 'first' => 'first_name', 'last' => 'last_name', 'sub' => 'id'],
        ],
    ],

    // --- Performance: hand large media transfers to the web server -----------
    // By default the app streams course media through PHP. Under load, offload
    // the byte-pushing to your web server so PHP workers free up immediately.
    // Enable exactly ONE of these to match your stack (see DEPLOYMENT.md):
    //
    // nginx (add an internal location aliased to your courses dir):
    //   'x_accel_redirect' => '/__protected_courses',
    //
    // Apache/lighttpd with mod_xsendfile enabled:
    //   'x_sendfile' => true,
    'x_accel_redirect' => '',
    'x_sendfile'       => false,
];
