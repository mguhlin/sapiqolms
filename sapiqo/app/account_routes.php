<?php
declare(strict_types=1);
route('GET', '/mfa/challenge', function () {
    $pending = $_SESSION['mfa_pending'] ?? null;
    if (!$pending || $pending['expires'] < time()) { unset($_SESSION['mfa_pending']); redirect('/login'); }
    view('mfa', [], 'Two-step verification');
});
route('POST', '/mfa/challenge', function () {
    csrf_check(); $pending = $_SESSION['mfa_pending'] ?? null;
    if (!$pending || $pending['expires'] < time()) { unset($_SESSION['mfa_pending']); redirect('/login'); }
    $ident = 'mfa:' . $pending['uid'];
    $user = db_one('SELECT * FROM users WHERE id=?', [$pending['uid']]);
    if (login_is_blocked($ident)[0] || !$user || (int)($user['session_version'] ?? 0) !== $pending['version'] || !mfa_verify((int)$user['id'], input('code'))) {
        login_record_attempt($ident, false); flash('Verification failed or temporarily rate limited.', 'error'); redirect('/mfa/challenge');
    }
    login_clear_failures($ident); login_user($user, true); audit('login.mfa_success');
    code_apply_pending((int)$user['id']);
    $to = $_SESSION['after_login'] ?? '/dashboard'; unset($_SESSION['after_login']); redirect($to);
});
route('GET', '/profile/security', function () {
    require_login(); if (is_impersonating()) { http_response_code(403); exit('Account security is unavailable during impersonation.'); }
    $user = current_user();
    if (empty($user['mfa_secret']) && (empty($_SESSION['mfa_setup']) || $_SESSION['mfa_setup']['expires'] < time())) $_SESSION['mfa_setup'] = ['secret' => mfa_base32_encode(random_bytes(20)), 'expires' => time()+600];
    $recovery = $_SESSION['mfa_codes'] ?? []; unset($_SESSION['mfa_codes']);
    view('account_security', ['user' => $user, 'setup' => $_SESSION['mfa_setup']['secret'] ?? '', 'recovery' => $recovery,
        'identities' => db_all('SELECT provider FROM user_identities WHERE user_id=?', [(int)$user['id']]), 'providers' => sso_providers()], 'Account security');
});
route('POST', '/profile/security', function () {
    csrf_check(); require_login(); $user = current_user(); $uid = (int)$user['id']; $ident = 'security:' . $uid;
    if (login_is_blocked($ident)[0] || !security_reauthenticate($user)) {
        login_record_attempt($ident, false); flash('Enter your current password or a fresh verification/recovery code. Try again later if rate limited.', 'error'); redirect('/profile/security');
    }
    login_clear_failures($ident);
    switch (input('action')) {
        case 'enable':
            $setup = $_SESSION['mfa_setup'] ?? null;
            if (!empty($user['mfa_secret']) || !$setup || $setup['expires'] < time() || ($counter = mfa_counter($setup['secret'], input('setup_code'))) === null) {
                flash('Enter the six-digit code from your authenticator. Setup expires after ten minutes.', 'error'); redirect('/profile/security');
            }
            $codes = []; for ($i=0; $i<10; $i++) $codes[] = bin2hex(random_bytes(8));
            db_run('UPDATE users SET mfa_secret=?,mfa_last_counter=?,mfa_recovery=? WHERE id=?', [mfa_encrypt($setup['secret']), $counter, json_encode(array_map(fn($v)=>hash('sha256',$v),$codes)), $uid]);
            $_SESSION['mfa_codes'] = $codes; unset($_SESSION['mfa_setup']); revoke_user_sessions($uid);
            $_SESSION['session_version'] = (int)db_one('SELECT session_version FROM users WHERE id=?', [$uid])['session_version'];
            audit('mfa.enabled'); flash('Two-step verification enabled. Save the recovery codes now.'); break;
        case 'disable':
            db_run('UPDATE users SET mfa_secret=NULL,mfa_recovery=NULL,mfa_last_counter=-1 WHERE id=?', [$uid]); revoke_user_sessions($uid);
            $_SESSION['session_version'] = (int)db_one('SELECT session_version FROM users WHERE id=?', [$uid])['session_version'];
            audit('mfa.disabled'); flash('Two-step verification disabled. Other sessions were revoked.'); break;
        case 'revoke':
            revoke_user_sessions($uid); unset($_SESSION['uid'], $_SESSION['csrf']); flash('All sessions revoked. Sign in again.'); redirect('/login');
        case 'link':
            $provider = input('provider'); if (!isset(sso_providers()[$provider])) { flash('Provider is not configured.', 'error'); break; }
            $_SESSION['sso_link'] = ['uid' => $uid, 'expires' => time()+300]; sso_begin($provider, true);
        case 'unlink':
            db_run('DELETE FROM user_identities WHERE user_id=? AND provider=?', [$uid, input('provider')]); audit('identity.unlinked'); flash('Additional sign-in method removed. Your original sign-in method remains available.'); break;
    }
    redirect('/profile/security');
});
