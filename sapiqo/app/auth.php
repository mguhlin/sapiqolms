<?php
// Authentication: local email+password plus a pluggable SSO layer.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function auth_boot(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $cfg = lms_config();
        // Reject uninitialized session IDs (session fixation hardening).
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($cfg['session_name']);
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => request_is_https(),
        ]);
        session_start();
    }
}

// Idle-session timeout (Admin -> Settings -> Accounts; 0 = disabled, the
// pre-existing behavior). Sessions are plain browser-session cookies with no
// fixed expiry, so without this a signed-in tab can stay authenticated
// indefinitely as long as it's never closed. Checked on every request that
// looks up the current user; if the gap since the last request exceeds the
// configured minutes, the session is cleared (a flash message survives the
// clear) and current_user() reports "not logged in" — every existing route
// guard (require_login/require_admin/etc.) already handles that correctly,
// so no separate redirect logic is needed here.
function session_idle_timeout_check(): void {
    if (empty($_SESSION['uid'])) return;
    $minutes = (int) (lms_config()['session_idle_timeout_minutes'] ?? 0);
    if ($minutes <= 0) { $_SESSION['last_activity'] = time(); return; }
    $last = (int) ($_SESSION['last_activity'] ?? time());
    if (time() - $last > $minutes * 60) {
        unset($_SESSION['uid'], $_SESSION['impersonator']);
        if (function_exists('flash')) flash('You were signed out after ' . $minutes . ' minutes of inactivity.', 'error');
        return;
    }
    $_SESSION['last_activity'] = time();
}

function current_user(): ?array {
    session_idle_timeout_check();
    if (empty($_SESSION['uid'])) return null;
    static $cache = null;
    if ($cache && $cache['id'] === $_SESSION['uid']) return $cache;
    $cache = db_one('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
    return $cache;
}

function is_logged_in(): bool { return current_user() !== null; }

// May a brand-new account be created right now (by any method — email/password
// or SSO)? True if open self-registration is on, OR the visitor is carrying a
// validated enrollment code (set in $_SESSION by the /redeem flow) — so an
// organization can turn general sign-up off while still letting invited people
// create an account. See /redeem, /register, and the SSO callback.
function registration_allowed(): bool {
    if (!empty(lms_config()['allow_self_registration'])) return true;
    return !empty($_SESSION['pending_code']);
}

function is_admin(): bool {
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function require_login(): void {
    if (!is_logged_in()) {
        if (want_json()) json_out(['error' => 'Not authenticated'], 401);
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? url('/dashboard');
        redirect('/login');
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Forbidden: administrator access required.');
    }
}

// True if the current learner manages at least one group (sub-admin).
function manages_any_groups(): bool {
    $u = current_user();
    return $u !== null && !empty(managed_group_ids((int) $u['id']));
}

// Course-developer capability: may author/edit course content. Admins always can.
function can_edit_content(): bool {
    $u = current_user();
    return $u !== null && ($u['role'] === 'admin' || (int) ($u['can_edit_content'] ?? 0) === 1);
}

// Guard for course-authoring routes: full admins or granted course developers.
function require_content_access(): void {
    require_login();
    if (can_edit_content()) return;
    if (want_json()) json_out(['error' => 'Course-editing access required'], 403);
    http_response_code(403);
    exit('Forbidden: course-editing access required.');
}

// Allow full admins, or a manager of the specified group — directly or via the
// organization the group belongs to. Otherwise 403.
function require_group_access(int $groupId): void {
    require_login();
    if (is_admin()) return;
    $u = current_user();
    if (in_array($groupId, managed_group_ids((int) $u['id']), true)) return;
    http_response_code(403);
    exit('Forbidden: you do not manage this group.');
}

// Allow full admins, or a manager of the specified organization. Otherwise 403.
function require_org_access(int $orgId): void {
    require_login();
    if (is_admin()) return;
    $u = current_user();
    if (is_org_manager((int) $u['id'], $orgId)) return;
    http_response_code(403);
    exit('Forbidden: you do not manage this organization.');
}

// Does the current actor hold $perm for an organization? Admins yes.
function manager_allows_org(int $orgId, string $perm): bool {
    if (is_admin()) return true;
    $u = current_user();
    return $u !== null && org_manager_perm((int) $u['id'], $orgId, $perm);
}

// Allow full admins, or a manager who shares a group with the target user.
function require_user_access(int $targetId): void {
    require_login();
    if (is_admin()) return;
    $u = current_user();
    if (manager_can_edit_user((int) $u['id'], $targetId)) return;
    http_response_code(403);
    exit('Forbidden: you do not manage this user.');
}

// Does the current actor hold $perm ('enroll'|'members') for a group? Admins yes.
function manager_allows_group(int $groupId, string $perm): bool {
    if (is_admin()) return true;
    $u = current_user();
    return $u !== null && manager_perm((int) $u['id'], $groupId, $perm);
}

// Does the current actor hold $perm over a specific target user? Admins yes.
function manager_allows_user(int $targetId, string $perm): bool {
    if (is_admin()) return true;
    $u = current_user();
    return $u !== null && manager_perm_over_user((int) $u['id'], $targetId, $perm);
}

function login_user(array $user): void {
    session_regenerate_id(true);
    unset($_SESSION['impersonator'], $_SESSION['csrf']);
    $_SESSION['last_activity'] = time();
    $_SESSION['uid'] = (int) $user['id'];
}

// --- Impersonation ("view as user") ------------------------------------------
// An admin can act as another user for troubleshooting. The real admin's id is
// stashed in the session so they can switch back with a single click. While
// impersonating, current_user()/is_admin() reflect the *target*, so the admin
// sees exactly what that user sees and cannot reach admin-only routes.

function is_impersonating(): bool {
    return !empty($_SESSION['impersonator']);
}

function impersonator_user(): ?array {
    if (empty($_SESSION['impersonator'])) return null;
    return db_one('SELECT * FROM users WHERE id = ?', [(int) $_SESSION['impersonator']]);
}

function begin_impersonation(int $targetId): void {
    session_regenerate_id(true);                 // new id on privilege change; keeps $_SESSION
    $_SESSION['impersonator'] = (int) $_SESSION['uid'];
    $_SESSION['uid'] = $targetId;
}

// Restore the original admin. Returns the admin id, or null if not impersonating.
function end_impersonation(): ?int {
    if (empty($_SESSION['impersonator'])) return null;
    $adminId = (int) $_SESSION['impersonator'];
    session_regenerate_id(true);
    unset($_SESSION['impersonator']);
    $_SESSION['uid'] = $adminId;
    return $adminId;
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// Create a local account. Returns [ok, userIdOrErrorMessage].
function register_local(array $data): array {
    $email = strtolower(trim($data['email'] ?? ''));
    if (!valid_email($email)) return [false, 'Enter a valid email address.'];
    if (db_one('SELECT id FROM users WHERE email = ?', [$email])) {
        return [false, 'An account with that email already exists.'];
    }
    $pw = (string) ($data['password'] ?? '');
    if (strlen($pw) < 8) return [false, 'Password must be at least 8 characters.'];

    $now = now_utc();
    $id = db_insert(
        'INSERT INTO users (first_name,last_name,email,phone,password_hash,role,user_type,campus,organization,auth_provider,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            trim($data['first_name'] ?? ''),
            trim($data['last_name'] ?? ''),
            $email,
            trim($data['phone'] ?? ''),
            password_hash($pw, PASSWORD_DEFAULT),
            $data['role'] ?? 'learner',
            trim($data['user_type'] ?? ''),
            trim($data['campus'] ?? ''),
            trim($data['organization'] ?? ''),
            'local',
            $now, $now,
        ]
    );
    return [true, $id];
}

function verify_login(string $email, string $password): ?array {
    $email = strtolower(trim($email));
    $u = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$u || empty($u['password_hash'])) return null;
    if (!password_verify($password, $u['password_hash'])) return null;
    // Opportunistic rehash if the algorithm/cost changed.
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    return $u;
}

// --- Password reset ----------------------------------------------------------
// Tokens are single-use and time-limited. Only the SHA-256 hash is stored, so a
// leaked database cannot be used to reset accounts.

function create_reset_token(int $userId, int $ttlSeconds = 3600): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);
    // Invalidate any prior unused tokens for this user.
    db_run('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL', [$userId]);
    db_run('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?,?,?,?)',
        [$userId, $hash, $expires, now_utc()]);
    return $token;
}

// Returns the user row for a valid, unexpired, unused token, else null.
function user_for_reset_token(string $token): ?array {
    $token = trim($token);
    if ($token === '') return null;
    $hash = hash('sha256', $token);
    $row = db_one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL', [$hash]);
    // Belt-and-suspenders: the DB lookup above already does the real matching
    // (an indexed exact-match query, not a secret-dependent-time PHP compare),
    // but re-check with hash_equals() so this stays timing-safe even if a
    // future refactor changes the query to fetch by user/id and compare here.
    if (!$row || !hash_equals($row['token_hash'], $hash)) return null;
    if (strtotime($row['expires_at'] . ' UTC') < time()) return null;
    return db_one('SELECT * FROM users WHERE id = ?', [$row['user_id']]);
}

function consume_reset_token(string $token): void {
    $hash = hash('sha256', trim($token));
    db_run('UPDATE password_resets SET used_at = ? WHERE token_hash = ?', [now_utc(), $hash]);
}

function set_user_password(int $userId, string $password): void {
    db_run('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
        [password_hash($password, PASSWORD_DEFAULT), now_utc(), $userId]);
}

// --- SSO (config-gated) ------------------------------------------------------
// Providers are defined declaratively; the OAuth flow is generic. A provider
// only appears once it is enabled with real client credentials in config.local.

function sso_providers(): array {
    $cfg = lms_config()['sso'];
    $providers = [];
    if (!empty($cfg['google']['enabled']) && $cfg['google']['client_id']) {
        $providers['google'] = [
            'label'     => 'Google',
            'auth'      => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token'     => 'https://oauth2.googleapis.com/token',
            'userinfo'  => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope'     => 'openid email profile',
            'client_id' => $cfg['google']['client_id'],
            'secret'    => $cfg['google']['client_secret'],
        ];
    }
    if (!empty($cfg['microsoft']['enabled']) && $cfg['microsoft']['client_id']) {
        $tenant = $cfg['microsoft']['tenant'] ?: 'common';
        $providers['microsoft'] = [
            'label'     => 'Microsoft',
            'auth'      => "https://login.microsoftonline.com/$tenant/oauth2/v2.0/authorize",
            'token'     => "https://login.microsoftonline.com/$tenant/oauth2/v2.0/token",
            'userinfo'  => 'https://graph.microsoft.com/oidc/userinfo',
            'scope'     => 'openid email profile',
            'client_id' => $cfg['microsoft']['client_id'],
            'secret'    => $cfg['microsoft']['client_secret'],
        ];
    }
    // Clever (K-12 SSO/rostering). Token exchange uses HTTP Basic auth and the
    // user is resolved in two steps (/me -> /users/{id}).
    if (!empty($cfg['clever']['enabled']) && $cfg['clever']['client_id']) {
        $providers['clever'] = [
            'label'     => 'Clever',
            'auth'      => 'https://clever.com/oauth/authorize',
            'token'     => 'https://clever.com/oauth/tokens',
            'userinfo'  => 'https://api.clever.com/v3.0/me',
            'scope'     => 'read:user_id read:sis',
            'client_id' => $cfg['clever']['client_id'],
            'secret'    => $cfg['clever']['client_secret'],
            'flow'      => 'clever',
        ];
    }
    // ClassLink LaunchPad (K-12 SSO; many TX districts, incl. Skyward/Ascender
    // federated through it). Standard OAuth2 with differently-named userinfo fields.
    if (!empty($cfg['classlink']['enabled']) && $cfg['classlink']['client_id']) {
        $providers['classlink'] = [
            'label'     => 'ClassLink',
            'auth'      => 'https://launchpad.classlink.com/oauth2/v2/auth',
            'token'     => 'https://launchpad.classlink.com/oauth2/v2/token',
            'userinfo'  => 'https://nodeapi.classlink.com/v2/my/info',
            'scope'     => 'profile',
            'client_id' => $cfg['classlink']['client_id'],
            'secret'    => $cfg['classlink']['client_secret'],
            'map'       => ['email' => 'Email', 'first' => 'FirstName', 'last' => 'LastName', 'sub' => 'UserId'],
        ];
    }
    // Rhythm (K-12 SSO). Standard OAuth2/OIDC, but the endpoints and userinfo
    // field names vary by district tenant, so they're taken from config rather
    // than hard-coded. Set auth_url/token_url/userinfo_url (and optionally a
    // field map) to your Rhythm tenant. Falls back to OIDC-default field names.
    if (!empty($cfg['rhythm']['enabled']) && $cfg['rhythm']['client_id']
        && !empty($cfg['rhythm']['auth_url']) && !empty($cfg['rhythm']['token_url'])) {
        $providers['rhythm'] = [
            'label'     => 'Rhythm',
            'auth'      => $cfg['rhythm']['auth_url'],
            'token'     => $cfg['rhythm']['token_url'],
            'userinfo'  => $cfg['rhythm']['userinfo_url'] ?? '',
            'scope'     => $cfg['rhythm']['scope'] ?? 'openid email profile',
            'client_id' => $cfg['rhythm']['client_id'],
            'secret'    => $cfg['rhythm']['client_secret'],
            'map'       => $cfg['rhythm']['map'] ?? null,
        ];
    }
    return $providers;
}

function sso_redirect_uri(string $provider): string {
    return base_url_absolute() . '/auth/' . $provider . '/callback';
}

function sso_begin(string $provider): never {
    $p = sso_providers()[$provider] ?? null;
    if (!$p) redirect('/login');
    $_SESSION['sso_state'] = bin2hex(random_bytes(16));
    $_SESSION['sso_provider'] = $provider;
    $params = http_build_query([
        'client_id'     => $p['client_id'],
        'redirect_uri'  => sso_redirect_uri($provider),
        'response_type' => 'code',
        'scope'         => $p['scope'],
        'state'         => $_SESSION['sso_state'],
        'prompt'        => 'select_account',
    ]);
    redirect($p['auth'] . '?' . $params);
}

// Simple HTTP POST/GET helper (curl if present, stream fallback otherwise).
function http_request(string $method, string $url, array $opts = []): array {
    if (!preg_match('#^https://#i', $url)) return ['status' => 0, 'body' => ''];
    $headers = $opts['headers'] ?? [];
    $body = $opts['body'] ?? null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $code, 'body' => (string) $resp];
    }
    $ctx = stream_context_create(['http' => [
        'method'  => $method,
        'header'  => implode("\r\n", $headers),
        'content' => is_array($body) ? http_build_query($body) : ($body ?? ''),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 20,
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    return ['status' => $code, 'body' => (string) $resp];
}

// Handles the OAuth callback: exchanges code, fetches userinfo, and
// creates/links a local user. Returns [user-row-or-null, error-message].
// A null user with an empty error means "generic SSO failure" (bad/expired
// state, provider error, etc.); a non-empty error is shown to the visitor.
function sso_complete(string $provider, string $code, string $state): array {
    $p = sso_providers()[$provider] ?? null;
    if (!$p) return [null, ''];
    if (empty($_SESSION['sso_state']) || ($_SESSION['sso_provider'] ?? '') !== $provider
        || !hash_equals($_SESSION['sso_state'], $state)) return [null, ''];
    unset($_SESSION['sso_state'], $_SESSION['sso_provider']);

    // Resolve a normalized identity: ['email','first','last','sub','picture'].
    $id = ($p['flow'] ?? '') === 'clever'
        ? sso_identity_clever($p, $code, $provider)
        : sso_identity_oauth($p, $code, $provider);
    if (!$id || empty($id['email'])) return [null, ''];
    $email = strtolower(trim((string) $id['email']));
    if (!valid_email($email) || empty($id['sub'])) return [null, 'Invalid provider identity.'];

    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if ($user && (($user['auth_provider'] ?? '') !== $provider
        || (string) ($user['provider_sub'] ?? '') !== (string) $id['sub'])) {
        return [null, 'This email belongs to another sign-in method. Sign in using that method; automatic account linking is disabled.'];
    }
    if (!$user) {
        // No account yet: only create one if open registration is on, or the
        // visitor is carrying a validated enrollment code (same rule as /register).
        if (!registration_allowed()) {
            return [null, 'No account found for that email, and self-registration is currently closed. '
                . 'Ask your administrator for an account, or redeem your enrollment code first.'];
        }
        $now = now_utc();
        $newId = db_insert(
            'INSERT INTO users (first_name,last_name,email,role,auth_provider,provider_sub,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?)',
            [$id['first'] ?? '', $id['last'] ?? '', $email, 'learner', $provider, (string) ($id['sub'] ?? ''), $now, $now]
        );
        $user = db_one('SELECT * FROM users WHERE id = ?', [$newId]);
    }
    if (!empty($id['picture']) && function_exists('has_avatar') && !has_avatar((int) $user['id'])) {
        save_avatar_from_url((int) $user['id'], (string) $id['picture']);
    }
    return [$user, ''];
}

// Standard OAuth2 (Google/Microsoft/ClassLink): token in body, one userinfo call.
// Field names are mapped per provider (OIDC defaults: email/given_name/family_name).
function sso_identity_oauth(array $p, string $code, string $provider): ?array {
    $tok = http_request('POST', $p['token'], [
        'headers' => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        'body' => [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => sso_redirect_uri($provider),
            'client_id'     => $p['client_id'],
            'client_secret' => $p['secret'],
        ],
    ]);
    $token = json_decode($tok['body'], true);
    if (empty($token['access_token'])) return null;
    $ui = http_request('GET', $p['userinfo'], [
        'headers' => ['Authorization: Bearer ' . $token['access_token'], 'Accept: application/json'],
    ]);
    $info = json_decode($ui['body'], true) ?: [];
    if ($ui['status'] !== 200 || ($provider === 'google' && ($info['email_verified'] ?? false) !== true)) return null;
    $m = $p['map'] ?? ['email' => 'email', 'first' => 'given_name', 'last' => 'family_name', 'sub' => 'sub'];
    return [
        'email'   => $info[$m['email']] ?? ($info['email'] ?? ''),
        'first'   => $info[$m['first']] ?? '',
        'last'    => $info[$m['last']] ?? '',
        'sub'     => (string) ($info[$m['sub'] ?? 'sub'] ?? ($info['sub'] ?? '')),
        'picture' => $info['picture'] ?? '',
    ];
}

// Clever: token exchange uses HTTP Basic auth; the user is resolved via
// /me (returns the Clever user id) then /users/{id} (name + email).
function sso_identity_clever(array $p, string $code, string $provider): ?array {
    $basic = base64_encode($p['client_id'] . ':' . $p['secret']);
    $tok = http_request('POST', $p['token'], [
        'headers' => ['Authorization: Basic ' . $basic, 'Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        'body' => ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => sso_redirect_uri($provider)],
    ]);
    $token = json_decode($tok['body'], true);
    if (empty($token['access_token'])) return null;
    $bearer = ['Authorization: Bearer ' . $token['access_token'], 'Accept: application/json'];
    $me = json_decode(http_request('GET', $p['userinfo'], ['headers' => $bearer])['body'], true) ?: [];
    $uid = $me['data']['id'] ?? '';
    if ($uid === '') return null;
    $u = json_decode(http_request('GET', 'https://api.clever.com/v3.0/users/' . rawurlencode($uid), ['headers' => $bearer])['body'], true) ?: [];
    $d = $u['data'] ?? [];
    // Email can be at data.email or under a role (teacher/student/staff).
    $email = $d['email'] ?? '';
    if ($email === '' && !empty($d['roles']) && is_array($d['roles'])) {
        foreach ($d['roles'] as $role) { if (!empty($role['email'])) { $email = $role['email']; break; } }
    }
    return [
        'email' => $email,
        'first' => $d['name']['first'] ?? '',
        'last'  => $d['name']['last'] ?? '',
        'sub'   => (string) $uid,
        'picture' => '',
    ];
}

// Claim a reset token and change its owner's password in one transaction.
function reset_password_once(string $token, string $password): bool {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $hash = hash('sha256', trim($token));
        $claimed = db_run('UPDATE password_resets SET used_at=? WHERE token_hash=? AND used_at IS NULL AND expires_at>=?',
            [now_utc(), $hash, now_utc()]);
        if ($claimed->rowCount() !== 1) { $pdo->rollBack(); return false; }
        $row = db_one('SELECT user_id FROM password_resets WHERE token_hash=?', [$hash]);
        set_user_password((int) $row['user_id'], $password);
        $pdo->commit(); return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
