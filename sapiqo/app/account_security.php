<?php
// Focused account service: RFC 6238 TOTP, encrypted secrets, replay protection,
// single-use recovery codes and explicit provider linking.
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
function mfa_base32_encode(string $raw): string {
    $bits = ''; foreach (str_split($raw) as $byte) $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    $out = ''; foreach (str_split($bits, 5) as $chunk) $out .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}
function mfa_base32_decode(string $text): string {
    $bits = ''; foreach (str_split($text) as $c) { $pos = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $c); if ($pos === false) throw new InvalidArgumentException('Invalid secret.'); $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT); }
    $out = ''; foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $out .= chr(bindec($chunk)); return $out;
}
function mfa_totp(string $secret, int $counter, int $digits = 6): string {
    $hash = hash_hmac('sha1', pack('N2', intdiv($counter, 4294967296), $counter % 4294967296), mfa_base32_decode($secret), true);
    $offset = ord($hash[19]) & 15;
    $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string) ($number % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}
function mfa_counter(string $secret, string $code, ?int $now = null): ?int {
    if (!preg_match('/^[0-9]{6}$/D', $code)) return null;
    $counter = intdiv($now ?? time(), 30);
    for ($c = $counter - 1; $c <= $counter + 1; $c++) if ($c >= 0 && hash_equals(mfa_totp($secret, $c), $code)) return $c;
    return null;
}
function mfa_key(): string {
    $dir = lms_config()['data_dir']; if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Cannot create key directory.');
    $path = $dir . '/mfa.key';
    $handle = fopen($path, 'c+b'); if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('Cannot open MFA key.');
    try { chmod($path, 0600); $key = stream_get_contents($handle); if ($key === '') { $key = random_bytes(32); if (fwrite($handle, $key) !== 32 || !fflush($handle)) throw new RuntimeException('Cannot save MFA key.'); } if (strlen($key) !== 32) throw new RuntimeException('Invalid MFA key.'); return $key; }
    finally { flock($handle, LOCK_UN); fclose($handle); }
}
function mfa_encrypt(string $secret): string {
    $iv = random_bytes(12); $cipher = openssl_encrypt($secret, 'aes-256-gcm', mfa_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('Cannot encrypt MFA secret.'); return base64_encode($iv . $tag . $cipher);
}
function mfa_decrypt(string $encrypted): string {
    $raw = base64_decode($encrypted, true); if ($raw === false || strlen($raw) < 29) throw new RuntimeException('Invalid encrypted secret.');
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', mfa_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if ($plain === false) throw new RuntimeException('Cannot decrypt MFA secret. Restore the matching MFA key.'); return $plain;
}
function mfa_verify(int $uid, string $code): bool {
    $user = db_one('SELECT * FROM users WHERE id=?', [$uid]); if (!$user || empty($user['mfa_secret'])) return false;
    $counter = mfa_counter(mfa_decrypt($user['mfa_secret']), trim($code));
    if ($counter !== null) return db_run('UPDATE users SET mfa_last_counter=? WHERE id=? AND COALESCE(mfa_last_counter,-1) < (? + 0)', [$counter, $uid, $counter])->rowCount() === 1;
    $codes = json_decode($user['mfa_recovery'] ?: '[]', true); $hash = hash('sha256', trim($code));
    foreach ($codes as $i => $saved) if (hash_equals($saved, $hash)) {
        unset($codes[$i]); return db_run('UPDATE users SET mfa_recovery=? WHERE id=? AND mfa_recovery=?', [json_encode(array_values($codes)), $uid, $user['mfa_recovery']])->rowCount() === 1;
    }
    return false;
}
function revoke_user_sessions(int $uid): void { db_run('UPDATE users SET session_version=COALESCE(session_version,0)+1 WHERE id=?', [$uid]); }
function security_reauthenticate(array $user): bool {
    if (is_impersonating()) return false;
    if (!empty($user['mfa_secret'])) return mfa_verify((int)$user['id'], input('code'));
    if (empty($user['password_hash'])) return (int)($_SESSION['reauth_at'] ?? 0) >= time()-300;
    return password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash']);
}
function identity_link(int $uid, string $provider, string $subject): bool {
    if ($subject === '' || strlen($subject) > 191) return false;
    $original = db_one('SELECT id FROM users WHERE auth_provider=? AND provider_sub=?', [$provider,$subject]);
    if ($original && (int)$original['id'] !== $uid) return false;
    try { db_run('INSERT INTO user_identities (provider,subject,user_id,created_at) VALUES (?,?,?,?)', [$provider, $subject, $uid, now_utc()]); return true; }
    catch (PDOException $e) { if (str_starts_with((string)$e->getCode(), '23')) return false; throw $e; }
}
