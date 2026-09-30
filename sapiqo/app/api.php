<?php
// Public REST API: token-based auth + key management. Keys are shown once at
// creation; only the SHA-256 hash is stored. New keys use explicit scopes; legacy keys retain access until rotated.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Create a key. Returns [id, plaintextToken] (token shown once).
function api_key_create(string $name, ?int $createdBy, array $scopes = ['courses:read']): array {
    $scopes = array_values(array_intersect(array_unique($scopes), api_key_scopes()));
    if (!$scopes) throw new InvalidArgumentException('Select at least one API scope.');
    $token = 'sk_' . bin2hex(random_bytes(24));   // 51 chars
    $hash = hash('sha256', $token);
    $prefix = substr($token, 0, 10);
    $id = db_insert('INSERT INTO api_keys (name, prefix, token_hash, created_by, created_at, scopes) VALUES (?,?,?,?,?,?)',
        [$name !== '' ? $name : 'API key', $prefix, $hash, $createdBy, now_utc(), json_encode($scopes)]);
    return [$id, $token];
}

function api_keys_all(): array {
    return db_all('SELECT * FROM api_keys ORDER BY id DESC');
}

function api_key_revoke(int $id): void {
    db_run('UPDATE api_keys SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', [now_utc(), $id]);
}

// Validate the Bearer token from the request. Returns the key row or null.
function api_key_check(): ?array {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($hdr === '' && function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        $hdr = $h['Authorization'] ?? ($h['authorization'] ?? '');
    }
    if (!preg_match('/^Bearer\s+(\S+)/i', $hdr, $m)) return null;
    $row = db_one('SELECT * FROM api_keys WHERE token_hash = ? AND revoked_at IS NULL', [hash('sha256', $m[1])]);
    if (!$row) return null;
    db_run('UPDATE api_keys SET last_used_at = ? WHERE id = ?', [now_utc(), $row['id']]);
    return $row;
}

// Bounds blast radius if a key leaks: caps total requests (successful or not)
// per key in a rolling window, independent of client IP — a leaked key could
// be replayed from anywhere. Reuses the login_attempts table/ident convention
// (see security.php) rather than a dedicated table.
function api_key_rate_limited(int $keyId): bool {
    $windowSeconds = 300;   // 5 minutes
    $maxRequests = 300;     // generous for legitimate bulk sync; bounds abuse
    $since = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
    $n = (int) (db_one('SELECT COUNT(*) n FROM login_attempts WHERE ident = ? AND created_at >= ?',
        ['apikey:' . $keyId, $since])['n'] ?? 0);
    return $n >= $maxRequests;
}

// Guard for API routes. Emits 401 JSON and exits when unauthenticated, 429
// JSON and exits when the key's request rate is over the cap.
function api_key_scopes(): array { return ['courses:read','users:read','users:write','users:admin','enrollments:read','enrollments:write','completions:read','badges:read']; }

function api_key_allows(array $key, string $scope): bool {
    if (($key['scopes'] ?? null) === null) return true; // existing integrations remain compatible; rotate legacy keys
    return in_array($scope, json_decode($key['scopes'], true) ?: [], true);
}

function require_api_key(string $scope): array {
    $key = api_key_check();
    if (!$key) {
        header('Content-Type: application/json; charset=utf-8');
        header('WWW-Authenticate: Bearer');
        http_response_code(401);
        echo json_encode(['error' => 'Missing or invalid API key. Send: Authorization: Bearer <token>']);
        exit;
    }
    if (api_key_rate_limited((int) $key['id'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded for this API key. Try again shortly.']);
        exit;
    }
    if (!api_key_allows($key, $scope)) json_out(['error' => 'API key lacks required scope: ' . $scope], 403);
    login_record_attempt('apikey:' . $key['id'], true);
    return $key;
}

// Read a JSON (or form) request body as an array.
function api_body(): array {
    $raw = file_get_contents('php://input');
    $j = json_decode((string) $raw, true);
    return is_array($j) ? $j : $_POST;
}
