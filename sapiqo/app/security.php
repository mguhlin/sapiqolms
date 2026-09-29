<?php
// Security helpers: client IP, audit trail, and login throttling.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Best-effort client IP. Honors X-Forwarded-For only when a trusted proxy is
// configured (config 'trusted_proxies'), otherwise uses the socket peer.
function client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $trusted = lms_config()['trusted_proxies'] ?? [];
    if ($trusted && in_array($remote, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $ip = $parts[0] ?? $remote;
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $remote !== '' ? $remote : '0.0.0.0';
}

// Send baseline security headers on every response. CSP allows the inline
// styles/scripts the views use, Google Fonts, and the Vimeo/YouTube players the
// course reader embeds; everything else defaults to same-origin.
function send_security_headers(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0'); // modern browsers rely on CSP; legacy filter off
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    $csp = "default-src 'self'; "
         . "img-src 'self' data: https:; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com data:; "
         . "script-src 'self' 'nonce-" . (function_exists('csp_nonce') ? csp_nonce() : '') . "'; "
         . "media-src 'self' blob: https://*.dropboxusercontent.com; "
         . "frame-src 'self' https:; "   // course authors embed HTML/docs/video from many HTTPS sources
         . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
    header('Content-Security-Policy: ' . $csp);
    // HSTS only over HTTPS (avoid locking out plain-HTTP dev/LAN installs).
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}

// --- Audit trail -------------------------------------------------------------

// Append an audit entry. Never throws — auditing must not break a request.
function audit(string $action, array $opts = []): void {
    try {
        $actor = function_exists('current_user') ? current_user() : null;
        db_run(
            'INSERT INTO audit_log (actor_id, actor_email, action, target_type, target_id, detail, ip, created_at)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $opts['actor_id'] ?? ($actor['id'] ?? null),
                $opts['actor_email'] ?? ($actor['email'] ?? ''),
                $action,
                (string) ($opts['target_type'] ?? ''),
                (string) ($opts['target_id'] ?? ''),
                is_array($opts['detail'] ?? null) ? json_encode($opts['detail']) : (string) ($opts['detail'] ?? ''),
                client_ip(),
                now_utc(),
            ]
        );
    } catch (Throwable $e) {
        error_log('audit: ' . $e->getMessage());
    }
}

function audit_recent(int $limit = 200, string $q = '', string $action = ''): array {
    $where = []; $args = [];
    if ($q !== '') {
        $where[] = '(actor_email LIKE ? OR target_id LIKE ? OR detail LIKE ?)';
        $like = '%' . $q . '%'; array_push($args, $like, $like, $like);
    }
    if ($action !== '') { $where[] = 'action = ?'; $args[] = $action; }
    $sql = 'SELECT * FROM audit_log' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY id DESC LIMIT ' . max(1, min(2000, $limit));
    return db_all($sql, $args);
}

function audit_actions(): array {
    return array_column(db_all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action');
}

// --- Login throttling --------------------------------------------------------
// Sliding-window lockout: too many recent failures for an email OR from an IP
// blocks further attempts until the window passes. Successful logins clear the
// account's recent failures.

function login_throttle_config(): array {
    $c = lms_config();
    return [
        'max_per_account' => (int) ($c['login_max_per_account'] ?? 5),
        'max_per_ip'      => (int) ($c['login_max_per_ip'] ?? 15),
        'window_seconds'  => (int) ($c['login_window_seconds'] ?? 900), // 15 min
    ];
}

function _login_window_start(int $seconds): string {
    return gmdate('Y-m-d H:i:s', time() - $seconds);
}

function login_record_attempt(string $email, bool $success): void {
    db_run('INSERT INTO login_attempts (ident, ip, success, created_at) VALUES (?,?,?,?)',
        [strtolower(trim($email)), client_ip(), $success ? 1 : 0, now_utc()]);
    // Occasionally prune old rows to keep the table small (1-in-20 requests).
    if (function_exists('random_int') && random_int(1, 20) === 1) {
        db_run('DELETE FROM login_attempts WHERE created_at < ?',
            [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]);
    }
}

function login_clear_failures(string $email): void {
    db_run('DELETE FROM login_attempts WHERE ident = ? AND success = 0', [strtolower(trim($email))]);
}

// Returns [blocked(bool), retryAfterSeconds(int)].
function login_is_blocked(string $email): array {
    $cfg = login_throttle_config();
    $since = _login_window_start($cfg['window_seconds']);
    $email = strtolower(trim($email));

    $byAccount = (int) (db_one(
        'SELECT COUNT(*) n FROM login_attempts WHERE ident = ? AND success = 0 AND created_at >= ?',
        [$email, $since]
    )['n'] ?? 0);
    $byIp = (int) (db_one(
        'SELECT COUNT(*) n FROM login_attempts WHERE ip = ? AND success = 0 AND created_at >= ?',
        [client_ip(), $since]
    )['n'] ?? 0);

    if ($byAccount >= $cfg['max_per_account'] || $byIp >= $cfg['max_per_ip']) {
        // Retry-after = time until the oldest in-window failure ages out.
        $oldest = db_one(
            'SELECT MIN(created_at) t FROM login_attempts
             WHERE success = 0 AND created_at >= ? AND (ident = ? OR ip = ?)',
            [$since, $email, client_ip()]
        );
        $retry = $cfg['window_seconds'];
        if (!empty($oldest['t'])) {
            $retry = max(30, $cfg['window_seconds'] - (time() - strtotime($oldest['t'] . ' UTC')));
        }
        return [true, (int) $retry];
    }
    return [false, 0];
}
