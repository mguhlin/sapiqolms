<?php
// LTI 1.3 Advantage — tool provider. Lets Sapiqo be launched as an external
// tool from Canvas / Moodle / Blackboard / Schoology, with user provisioning
// and (optional) AGS grade passback. Implements JWT (RS256) + JWKS by hand
// (no JWT library needed); uses the openssl extension for signing/verifying.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';   // http_request()

// --- base64url ---------------------------------------------------------------
function b64u_encode(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function b64u_decode(string $s): string {
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4; if ($pad) $s .= str_repeat('=', 4 - $pad);
    return base64_decode($s) ?: '';
}

// --- minimal DER for building an RSA public-key PEM from a JWK ---------------
function _der_len(int $n): string {
    if ($n < 0x80) return chr($n);
    $s = ''; while ($n > 0) { $s = chr($n & 0xff) . $s; $n >>= 8; }
    return chr(0x80 | strlen($s)) . $s;
}
function _der_tlv(int $tag, string $val): string { return chr($tag) . _der_len(strlen($val)) . $val; }
function _der_uint(string $bytes): string {
    $bytes = ltrim($bytes, "\x00"); if ($bytes === '') $bytes = "\x00";
    if (ord($bytes[0]) & 0x80) $bytes = "\x00" . $bytes;   // keep positive
    return _der_tlv(0x02, $bytes);
}
// RSA public key (n,e as base64url) -> SubjectPublicKeyInfo PEM.
function jwk_to_pem(array $jwk): string {
    $n = b64u_decode($jwk['n'] ?? ''); $e = b64u_decode($jwk['e'] ?? '');
    if ($n === '' || $e === '') return '';
    $rsa = _der_tlv(0x30, _der_uint($n) . _der_uint($e));
    $algo = _der_tlv(0x30, _der_tlv(0x06, hex2bin('2a864886f70d010101')) . _der_tlv(0x05, ''));
    $spki = _der_tlv(0x30, $algo . _der_tlv(0x03, "\x00" . $rsa));
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

// --- Sapiqo's own tool keypair (generated once, stored in data/lti) ----------
function lti_keys(): array {
    static $k = null;
    if ($k !== null) return $k;
    $dir = rtrim(lms_config()['data_dir'], '/') . '/lti';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $privFile = $dir . '/private.pem'; $kidFile = $dir . '/kid';
    if (!is_file($privFile)) {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $priv);
        file_put_contents($privFile, $priv);
        $details = openssl_pkey_get_details($res);
        file_put_contents($dir . '/public.pem', $details['key']);
        file_put_contents($kidFile, substr(hash('sha256', $details['key']), 0, 16));
    }
    $priv = (string) file_get_contents($privFile);
    $details = openssl_pkey_get_details(openssl_pkey_get_private($priv));
    $k = ['private' => $priv, 'public' => $details['key'], 'kid' => trim((string) file_get_contents($kidFile)),
          'n' => b64u_encode($details['rsa']['n']), 'e' => b64u_encode($details['rsa']['e'])];
    return $k;
}

// Sapiqo's public JWKS (for the platform to verify our client assertions).
function lti_jwks(): array {
    $k = lti_keys();
    return ['keys' => [[
        'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $k['kid'],
        'n' => $k['n'], 'e' => $k['e'],
    ]]];
}

// --- JWT ---------------------------------------------------------------------
function jwt_sign(array $payload, ?string $privatePem = null, ?string $kid = null): string {
    $k = lti_keys();
    $privatePem = $privatePem ?? $k['private'];
    $kid = $kid ?? $k['kid'];
    $header = ['typ' => 'JWT', 'alg' => 'RS256', 'kid' => $kid];
    $signingInput = b64u_encode(json_encode($header)) . '.' . b64u_encode(json_encode($payload));
    $sig = '';
    openssl_sign($signingInput, $sig, openssl_pkey_get_private($privatePem), OPENSSL_ALGO_SHA256);
    return $signingInput . '.' . b64u_encode($sig);
}

// Verify a JWT against a platform's keys. Returns claims array or null.
function jwt_verify(string $jwt, array $platform): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $header = json_decode(b64u_decode($h), true);
    $claims = json_decode(b64u_decode($p), true);
    if (!is_array($header) || !is_array($claims)) return null;
    if (($header['alg'] ?? '') !== 'RS256') return null;
    $pem = lti_platform_pem($platform, $header['kid'] ?? '');
    if ($pem === '') return null;
    $ok = openssl_verify($h . '.' . $p, b64u_decode($s), $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) return null;
    if (!isset($claims['exp'], $claims['iat']) || !is_numeric($claims['exp'])
        || !is_numeric($claims['iat']) || (int) $claims['iat'] > time() + 60) return null;
    if (time() >= (int) $claims['exp'] + 60) return null;   // 60s skew
    if (isset($claims['nbf']) && time() < (int) $claims['nbf'] - 60) return null;
    return $claims;
}

// Resolve a platform's verification PEM: inline public_key, else fetch JWKS by kid.
function lti_platform_pem(array $platform, string $kid): string {
    if (trim((string) ($platform['public_key'] ?? '')) !== '') return $platform['public_key'];
    $url = trim((string) ($platform['jwks_url'] ?? ''));
    if ($url === '') return '';
    static $cache = [];
    if (!isset($cache[$url])) {
        $resp = http_request('GET', $url, ['headers' => ['Accept: application/json']]);
        $cache[$url] = json_decode($resp['body'] ?? '', true) ?: ['keys' => []];
    }
    $jwks = $cache[$url];
    foreach ($jwks['keys'] ?? [] as $jwk) {
        if (($kid === '' || ($jwk['kid'] ?? '') === $kid) && ($jwk['kty'] ?? '') === 'RSA') {
            $pem = jwk_to_pem($jwk);
            if ($pem !== '') return $pem;
        }
    }
    return '';
}

// --- Platform registry -------------------------------------------------------
function lti_platform_create(array $d): int {
    return db_insert(
        'INSERT INTO lti_platforms (name,issuer,client_id,deployment_id,auth_login_url,auth_token_url,jwks_url,public_key,created_at)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$d['name'] ?? '', $d['issuer'] ?? '', $d['client_id'] ?? '', $d['deployment_id'] ?? '',
         $d['auth_login_url'] ?? '', $d['auth_token_url'] ?? '', $d['jwks_url'] ?? '', $d['public_key'] ?? '', now_utc()]
    );
}
function lti_platforms_all(): array { return db_all('SELECT * FROM lti_platforms ORDER BY id DESC'); }
function lti_platform_by_id(int $id): ?array { return db_one('SELECT * FROM lti_platforms WHERE id=?', [$id]); }
function lti_platform_delete(int $id): void { db_run('DELETE FROM lti_platforms WHERE id=?', [$id]); }
function lti_platform_find(string $issuer, string $clientId = ''): ?array {
    if ($clientId !== '') {
        $r = db_one('SELECT * FROM lti_platforms WHERE issuer=? AND client_id=?', [$issuer, $clientId]);
        if ($r) return $r;
    }
    return db_one('SELECT * FROM lti_platforms WHERE issuer=? ORDER BY id LIMIT 1', [$issuer]);
}

const LTI_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/';
const AGS_CLAIM = 'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint';
const AGS_SCOPE_SCORE = 'https://purl.imsglobal.org/spec/lti-ags/scope/score';

// --- OIDC third-party login initiation (/lti/login) --------------------------
// Redirects the browser to the platform's auth endpoint. $req = $_GET merged $_POST.
function lti_oidc_login(array $req): void {
    $iss = (string) ($req['iss'] ?? '');
    $platform = $iss !== '' ? lti_platform_find($iss, (string) ($req['client_id'] ?? '')) : null;
    if (!$platform) { http_response_code(400); exit('Unknown LTI issuer.'); }

    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    // Store in the DB (not the session) so the cross-site form_post launch works
    // even without a SameSite cookie. Prune anything older than ~10 minutes.
    db_run('DELETE FROM lti_state WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 600)]);
    db_run('INSERT INTO lti_state (state,nonce,platform_id,target,created_at) VALUES (?,?,?,?,?)',
        [$state, $nonce, (int) $platform['id'], (string) ($req['target_link_uri'] ?? ''), now_utc()]);

    $params = [
        'scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post',
        'prompt' => 'none', 'client_id' => $platform['client_id'],
        'redirect_uri' => lti_tool_urls()['launch_url'],
        'login_hint' => (string) ($req['login_hint'] ?? ''),
        'lti_message_hint' => (string) ($req['lti_message_hint'] ?? ''),
        'state' => $state, 'nonce' => $nonce,
    ];
    header('Location: ' . $platform['auth_login_url'] . '?' . http_build_query($params));
    exit;
}

// --- Launch (/lti/launch): verify id_token, provision, launch ----------------
// Returns [ok, redirectPathOrError].
function lti_launch(array $post): array {
    $idToken = (string) ($post['id_token'] ?? '');
    $state = (string) ($post['state'] ?? '');
    $sess = $state !== '' ? db_one('SELECT * FROM lti_state WHERE state = ?', [$state]) : null;
    if ($idToken === '' || !$sess) return [false, 'Invalid or expired LTI launch state.'];
    db_run('DELETE FROM lti_state WHERE state = ?', [$state]);   // single-use
    if (strtotime($sess['created_at'] . ' UTC') < time() - 600) return [false, 'LTI launch expired.'];
    $platform = lti_platform_by_id((int) $sess['platform_id']);
    if (!$platform) return [false, 'Unknown LTI platform.'];

    $claims = jwt_verify($idToken, $platform);
    if (!$claims) return [false, 'Could not verify the launch token signature.'];

    // Validate core claims.
    if (($claims['iss'] ?? '') !== $platform['issuer'] || empty($claims['sub'])) return [false, 'LTI issuer or subject mismatch.'];
    if (($claims[LTI_CLAIM . 'version'] ?? '') !== '1.3.0') return [false, 'Unsupported LTI version.'];
    if (($claims['nonce'] ?? '') !== $sess['nonce']) return [false, 'LTI nonce mismatch.'];
    $aud = $claims['aud'] ?? '';
    $audOk = is_array($aud) ? in_array($platform['client_id'], $aud, true) : ($aud === $platform['client_id']);
    if (!$audOk) return [false, 'LTI audience mismatch.'];
    if (is_array($aud) && count($aud) > 1 && ($claims['azp'] ?? '') !== $platform['client_id']) return [false, 'LTI authorized party mismatch.'];
    $dep = $claims[LTI_CLAIM . 'deployment_id'] ?? '';
    if ($platform['deployment_id'] !== '' && $dep !== $platform['deployment_id']) return [false, 'LTI deployment mismatch.'];
    $mt = $claims[LTI_CLAIM . 'message_type'] ?? '';
    if ($mt !== 'LtiResourceLinkRequest') return [false, 'Unsupported LTI message type.'];

    // Provision + log in the user.
    $user = lti_provision_user($claims, $platform);
    if (!$user) return [false, 'Could not provision the LTI user.'];
    audit('lti.launch', ['actor_id' => (int) $user['id'], 'actor_email' => $user['email'], 'detail' => $platform['name']]);

    // Which course? A custom 'course' claim (slug) selects it.
    $custom = $claims[LTI_CLAIM . 'custom'] ?? [];
    $slug = is_array($custom) ? (string) ($custom['course'] ?? $custom['sapiqo_course'] ?? '') : '';
    $course = $slug !== '' ? course_by_slug($slug) : null;

    if ($course) {
        enroll((int) $user['id'], (int) $course['id']);
        lti_capture_ags((int) $user['id'], (int) $course['id'], $platform, $claims);
        $target = '/courses/' . $course['slug'] . '/';
        if (!empty($user['mfa_secret'])) $_SESSION['after_login'] = $target;
        login_user($user);
        return [true, $target];
    }
    if (!empty($user['mfa_secret'])) $_SESSION['after_login'] = '/dashboard';
    login_user($user);
    return [true, '/dashboard'];
}

function lti_provision_user(array $claims, array $platform): ?array {
    // Platform subjects are authoritative; email is display data, not a local
    // account-linking credential. Platform roles never grant site administration.
    $sub = (string) ($claims['sub'] ?? '');
    if ($sub === '') return null;
    $identity = hash('sha256', $platform['issuer'] . '|' . $platform['client_id'] . '|' . $sub);
    $email = 'lti-' . substr($identity, 0, 48) . '@lti.local';
    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user) {
        [$ok, $res] = register_local([
            'first_name' => $claims['given_name'] ?? '', 'last_name' => $claims['family_name'] ?? '',
            'email' => $email, 'password' => bin2hex(random_bytes(32)), 'role' => 'learner',
        ]);
        if (!$ok) return null;
        db_run('UPDATE users SET auth_provider=?, provider_sub=? WHERE id=?', ['lti', $identity, $res]);
        $user = db_one('SELECT * FROM users WHERE id = ?', [$res]);
    }
    return $user;
}

// --- AGS (Assignment & Grade Services) ---------------------------------------
function lti_capture_ags(int $userId, int $courseId, array $platform, array $claims): void {
    $ags = $claims[AGS_CLAIM] ?? null;
    if (!is_array($ags) || empty($ags['lineitem'])) return;
    $scopes = implode(' ', (array) ($ags['scope'] ?? []));
    $sub = (string) ($claims['sub'] ?? '');
    $exists = db_one('SELECT id FROM lti_results WHERE user_id=? AND course_id=?', [$userId, $courseId]);
    if ($exists) {
        db_run('UPDATE lti_results SET platform_id=?, lineitem=?, scopes=?, sub=?, updated_at=? WHERE id=?',
            [$platform['id'], $ags['lineitem'], $scopes, $sub, now_utc(), $exists['id']]);
    } else {
        db_run('INSERT INTO lti_results (user_id,course_id,platform_id,lineitem,scopes,sub,updated_at) VALUES (?,?,?,?,?,?,?)',
            [$userId, $courseId, $platform['id'], $ags['lineitem'], $scopes, $sub, now_utc()]);
    }
}

// Get an OAuth2 access token from the platform via a signed client assertion.
function lti_get_token(array $platform, string $scope): ?string {
    if (empty($platform['auth_token_url'])) return null;
    $now = time();
    $assertion = jwt_sign([
        'iss' => $platform['client_id'], 'sub' => $platform['client_id'],
        'aud' => $platform['auth_token_url'], 'iat' => $now, 'exp' => $now + 300,
        'jti' => bin2hex(random_bytes(12)),
    ]);
    $resp = http_request('POST', $platform['auth_token_url'], [
        'headers' => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        'body' => [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $assertion, 'scope' => $scope,
        ],
    ]);
    $tok = json_decode($resp['body'] ?? '', true);
    return $tok['access_token'] ?? null;
}

// Send a completion score (0-100) back to the platform for a course. Best-effort.
function lti_send_score(int $userId, int $courseId, int $scorePercent): bool {
    $row = db_one('SELECT r.*, p.auth_token_url, p.client_id FROM lti_results r
                   JOIN lti_platforms p ON p.id = r.platform_id
                   WHERE r.user_id=? AND r.course_id=?', [$userId, $courseId]);
    if (!$row || $row['lineitem'] === '' || stripos($row['scopes'], 'score') === false) return false;
    $platform = lti_platform_by_id((int) $row['platform_id']);
    $token = lti_get_token($platform, AGS_SCOPE_SCORE);
    if (!$token) { error_log('lti_send_score: no token'); return false; }
    $url = $row['lineitem'] . (str_contains($row['lineitem'], '?') ? '' : '') . '/scores';
    // If lineitem has a query string, insert /scores before it.
    if (str_contains($row['lineitem'], '?')) {
        [$path, $q] = explode('?', $row['lineitem'], 2);
        $url = $path . '/scores?' . $q;
    }
    $body = json_encode([
        'userId' => $row['sub'], 'scoreGiven' => $scorePercent, 'scoreMaximum' => 100,
        'activityProgress' => 'Completed', 'gradingProgress' => 'FullyGraded',
        'timestamp' => gmdate('c'),
    ]);
    $resp = http_request('POST', $url, [
        'headers' => ['Authorization: Bearer ' . $token, 'Content-Type: application/vnd.ims.lis.v1.score+json'],
        'body' => $body,
    ]);
    $ok = ($resp['status'] ?? 0) >= 200 && ($resp['status'] ?? 0) < 300;
    if (!$ok) error_log('lti_send_score: HTTP ' . ($resp['status'] ?? 0));
    return $ok;
}

// The tool's own URLs (for registering Sapiqo in a platform).
function lti_tool_urls(): array {
    $base = function_exists('base_url_absolute') ? base_url_absolute() : '';
    return [
        'login_url'    => $base . '/lti/login',
        'launch_url'   => $base . '/lti/launch',
        'jwks_url'     => $base . '/lti/jwks',
        'redirect_uris'=> [$base . '/lti/launch'],
    ];
}
