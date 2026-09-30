<?php
// Optional email. Disabled by default (no-op) so the LMS runs fully offline.
// Enable by adding a 'mail' block to config.local.php:
//
//   'mail' => [
//     'enabled'   => true,
//     'transport' => 'smtp',            // 'smtp' or 'mail' (PHP mail())
//     'from'      => 'lms@yourdistrict.org',
//     'from_name' => 'District LMS',
//     'smtp' => [
//       'host' => 'smtp.yourdistrict.org', 'port' => 587,
//       'user' => '...', 'pass' => '...', 'secure' => 'tls', // 'tls' | 'ssl' | ''
//     ],
//   ],
//
// To send via a Gmail/Google Workspace account (the same idea as the WP Mail
// SMTP / FluentSMTP "Gmail" option in WordPress — this app has no plugins, but
// the same relay works the same way): set 'smtp.host' => 'smtp.gmail.com',
// 'port' => 587, 'secure' => 'tls', 'user' => the Gmail address you're
// authenticating as, and 'pass' => a Gmail **App Password** (Gmail's normal
// login password will NOT work for SMTP — turn on 2-Step Verification for
// that account, then generate one at https://myaccount.google.com/apppasswords).
// If 'from' should be a DIFFERENT address than the Gmail account you're
// authenticating as (e.g. sending as registration@example.org via a separate
// Gmail login), that address must be a verified "Send mail as" alias in that
// Gmail account's Settings -> Accounts and Import -> "Send mail as" — otherwise
// Gmail will reject the send or silently substitute its own address. See
// Admin -> Mail in the app for a status page + a "send test email" button that
// reports exactly which SMTP step failed if something isn't set up right.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function mail_config(): array {
    $m = lms_config()['mail'] ?? [];
    return array_replace([
        'enabled'   => false,
        'transport' => 'mail',
        'from'      => 'no-reply@localhost',
        'from_name' => lms_config()['app_name'] ?? 'Sapiqo',
        'smtp'      => ['host' => 'localhost', 'port' => 25, 'user' => '', 'pass' => '', 'secure' => ''],
    ], is_array($m) ? $m : []);
}

function mail_enabled(): bool {
    return (bool) (mail_config()['enabled'] ?? false);
}

// A Date + Message-ID header pair. Several receiving servers (Gmail included)
// weigh a missing Date/Message-ID as a spam signal, so every send includes them.
function mail_id_headers(string $fromAddress): string {
    $domain = strpos($fromAddress, '@') !== false ? substr($fromAddress, strpos($fromAddress, '@') + 1) : 'localhost';
    return 'Date: ' . date('r') . "\r\n"
         . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . ">\r\n";
}

// Send a plain-text email. Returns true on success. Never throws.
function send_mail(string $to, string $subject, string $body): bool {
    return mail_send_diagnostic($to, $subject, $body)[0];
}

// Same as send_mail(), but returns [ok, detail] with a human-readable reason —
// which SMTP step failed and the server's own response, e.g. "AUTH LOGIN
// (password) failed: 535-5.7.8 Username and Password not accepted." Used by
// the Admin -> Mail "send test email" tool so a bad app password, wrong from
// address, etc. is diagnosable without reading the server error log. Ignores
// the 'enabled' flag (a test send should work even while mail is toggled off,
// so an admin can verify settings before turning it on).
function mail_send_diagnostic(string $to, string $subject, string $body): array {
    $cfg = mail_config();
    if (!filter_var($to,FILTER_VALIDATE_EMAIL) || !filter_var($cfg['from'],FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n\x00]/', $subject . $cfg['from_name'])) return [false,'Invalid email address or header.'];
    try {
        if (($cfg['transport'] ?? 'mail') === 'smtp') {
            return smtp_send($cfg, $to, $subject, $body);
        }
        $from = sprintf('%s <%s>', $cfg['from_name'], $cfg['from']);
        $headers = "From: $from\r\n" . mail_id_headers((string) $cfg['from'])
                 . "Content-Type: text/plain; charset=utf-8\r\nMIME-Version: 1.0\r\n";
        $ok = @mail($to, $subject, $body, $headers);
        return [$ok, $ok ? 'Handed to the local mail() function.' : 'PHP mail() returned false — check the server has a working local MTA (sendmail/postfix).'];
    } catch (Throwable $e) {
        error_log('send_mail: ' . $e->getMessage());
        return [false, $e->getMessage()];
    }
}

// Minimal SMTP client (AUTH LOGIN, optional STARTTLS / implicit SSL). Enough for
// common district relays and Gmail SMTP relay; for anything exotic, use a real
// MTA via transport 'mail'. Returns [ok, detail] — see mail_send_diagnostic().
function smtp_send(array $cfg, string $to, string $subject, string $body): array {
    if (!filter_var($to,FILTER_VALIDATE_EMAIL) || !filter_var($cfg['from'],FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n\x00]/', $subject . $cfg['from_name'])) return [false,'Invalid email address or header.'];
    $s = $cfg['smtp'];
    $secure = strtolower((string) ($s['secure'] ?? ''));
    $host = ($secure === 'ssl' ? 'ssl://' : '') . $s['host'];
    $fp = @fsockopen($host, (int) $s['port'], $errno, $errstr, 15);
    if (!$fp) { $msg = "Could not connect to {$s['host']}:{$s['port']} — $errstr"; error_log("smtp connect: $errstr"); return [false, $msg]; }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $last = '';
    $cmd = function (string $c, string $expect) use ($fp, $read, &$last): bool {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $last = trim($read());
        return str_starts_with($last, $expect);
    };

    $step = 'connect'; $ok = true;
    $ok = $ok && $cmd('', '220'); if (!$ok) $step = 'server greeting';
    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    if ($ok) { $ok = $cmd($ehlo, '250'); if (!$ok) $step = 'EHLO'; }
    if ($ok && $secure === 'tls') {
        $ok = $cmd('STARTTLS', '220'); if (!$ok) $step = 'STARTTLS';
        if ($ok && !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $ok = false; $step = 'TLS handshake'; $last = 'The server accepted STARTTLS but the TLS handshake itself failed.'; }
        if ($ok) { $ok = $cmd($ehlo, '250'); if (!$ok) $step = 'EHLO (after STARTTLS)'; }
    }
    if ($ok && !empty($s['user'])) {
        $ok = $cmd('AUTH LOGIN', '334'); if (!$ok) $step = 'AUTH LOGIN';
        if ($ok) { $ok = $cmd(base64_encode((string) $s['user']), '334'); if (!$ok) $step = 'AUTH LOGIN (username)'; }
        if ($ok) { $ok = $cmd(base64_encode((string) $s['pass']), '235'); if (!$ok) $step = 'AUTH LOGIN (password) — check the app password is correct and not expired/revoked'; }
    }
    if ($ok) { $ok = $cmd('MAIL FROM:<' . $cfg['from'] . '>', '250'); if (!$ok) $step = "MAIL FROM (the From address, \"{$cfg['from']}\", may need to be a verified \"Send as\" alias in that Gmail account)"; }
    if ($ok) { $ok = $cmd('RCPT TO:<' . $to . '>', '250'); if (!$ok) $step = 'RCPT TO (recipient address)'; }
    if ($ok) { $ok = $cmd('DATA', '354'); if (!$ok) $step = 'DATA'; }
    if ($ok) {
        $headers = 'From: ' . sprintf('%s <%s>', $cfg['from_name'], $cfg['from']) . "\r\n"
                 . 'To: ' . $to . "\r\n"
                 . 'Subject: ' . $subject . "\r\n"
                 . mail_id_headers((string) $cfg['from'])
                 . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n";
        // Dot-stuffing for lines beginning with '.'
        $safeBody = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $body));
        $safeBody = str_replace("\n", "\r\n", $safeBody);
        fwrite($fp, $headers . $safeBody . "\r\n.\r\n");
        $last = trim($read());
        $ok = str_starts_with($last, '250');
        if (!$ok) $step = 'message body (after DATA)';
    }
    $cmd('QUIT', '221');
    fclose($fp);
    return $ok ? [true, 'Sent — server said: ' . $last] : [false, "Failed at $step — server said: " . ($last !== '' ? $last : '(no response)')];
}
