<?php
/*
 * Sign-in flow (included from api.php). Steps kept in the PHP session:
 *   password ok  ->  'enroll' (first time: set up authenticator)  or  'totp' (enter 6-digit code)
 *   -> 'change_pw' (only when the master issued a temporary password)  ->  signed in
 * Two-factor authentication is always required; there is no way to turn it off.
 */
if (!defined('DB_PATH')) exit;

admin_session();

function pending_admin() {
    if (empty($_SESSION['pending_id'])) fail('Your sign-in timed out. Please start again.', 'RESTART');
    if (($_SESSION['pending_at'] ?? 0) < time() - 900) { unset($_SESSION['pending_id']); fail('Your sign-in timed out. Please start again.', 'RESTART'); }
    $a = admin_by_id($_SESSION['pending_id']);
    if (!$a) fail('Account not found.', 'RESTART');
    return $a;
}
function set_stage($a, $stage) {
    $_SESSION['pending_id'] = (int) $a['id'];
    $_SESSION['pending_stage'] = $stage;
    $_SESSION['pending_at'] = time();
}
function require_stage($stage) {
    $a = pending_admin();
    if (($_SESSION['pending_stage'] ?? '') !== $stage) fail('Please start signing in again.', 'RESTART');
    return $a;
}
/** After the second factor: maybe force a password change, otherwise finish signing in. */
function after_second_factor($a) {
    if ((int) $a['must_change_pw']) { set_stage($a, 'change_pw'); return 'change_pw'; }
    session_regenerate_id(true);
    unset($_SESSION['pending_id'], $_SESSION['pending_stage'], $_SESSION['enroll_secret']);
    $_SESSION['rq_admin_id'] = (int) $a['id'];
    qexec('UPDATE admins SET last_login = ?, failed_logins = 0, locked_until = 0 WHERE id = ?', [now_ms(), $a['id']]);
    return 'done';
}
function needs_enrollment($a) {
    return !(int) $a['totp_enabled'] || (is_master($a) && MASTER_RESET_2FA);
}
function note_failure($a) {
    $n = (int) $a['failed_logins'] + 1;
    qexec('UPDATE admins SET failed_logins = ?, locked_until = ? WHERE id = ?',
        [$n, $n >= 5 ? now_ms() + 15 * 60 * 1000 : (int) $a['locked_until'], $a['id']]);
    usleep(400000);
}

switch ($action) {

case 'auth_status':
    $signedIn = current_admin();
    out([
        'needMaster' => admins_count() === 0,
        'signedIn' => (bool) $signedIn,
        'stage' => $_SESSION['pending_stage'] ?? null,
        'masterPhone' => format_phone(MASTER_PHONE),
        'csrf' => $_SESSION['csrf'],
    ]);

case 'create_master':
    if (admins_count() > 0) fail('The master admin already exists. Please sign in.');
    if (preg_replace('/\D/', '', (string) in('phone_confirm')) !== preg_replace('/\D/', '', MASTER_PHONE))
        fail('That phone number doesn\'t match the master phone number for this installation.');
    if ((string) in('password') !== (string) in('password2')) fail('The two passwords don\'t match.');
    $id = create_admin(['username' => in('username'), 'password' => in('password'), 'display_name' => in('display_name'),
        'org' => in('org')], 'master');
    if ($id !== 1) {
        // the master must be account #1 so that data from older versions belongs to it
        qexec('UPDATE admins SET id = 1 WHERE id = ?', [$id]);
        $id = 1;
    }
    set_stage(admin_by_id($id), 'enroll');
    out(['stage' => 'enroll']);

case 'login':
    $a = q1('SELECT * FROM admins WHERE username = ?', [trim((string) in('username'))]);
    if (!$a) { usleep(500000); fail('Wrong username or password.'); }
    if ((int) $a['locked_until'] > now_ms())
        fail('Too many attempts. Try again in ' . ceil(((int) $a['locked_until'] - now_ms()) / 60000) . ' minutes.');
    if (!password_verify((string) in('password'), $a['password_hash'])) { note_failure($a); fail('Wrong username or password.'); }
    if ($r = account_block_reason($a)) fail($r);
    $stage = needs_enrollment($a) ? 'enroll' : 'totp';
    set_stage($a, $stage);
    out(['stage' => $stage, 'name' => $a['display_name']]);

case 'enroll_start':
    $a = require_stage('enroll');
    if (empty($_SESSION['enroll_secret'])) $_SESSION['enroll_secret'] = totp_new_secret();
    $sec = $_SESSION['enroll_secret'];
    out(['secret' => trim(chunk_split($sec, 4, ' ')), 'uri' => totp_uri($sec, $a['username']),
         'phone' => is_master($a) ? format_phone(MASTER_PHONE) : null, 'name' => $a['display_name']]);

case 'enroll_confirm':
    $a = require_stage('enroll');
    $sec = $_SESSION['enroll_secret'] ?? '';
    $step = $sec ? totp_verify($sec, in('code')) : false;
    if ($step === false) fail('That code didn\'t match. Make sure your phone\'s clock is set automatically, then try the newest code.');
    $codes = new_backup_codes();
    qexec('UPDATE admins SET totp_secret = ?, totp_enabled = 1, totp_last_step = ?, backup_codes = ? WHERE id = ?',
        [$sec, $step, json_encode(array_map(function ($c) { return password_hash($c, PASSWORD_DEFAULT); }, $codes)), $a['id']]);
    unset($_SESSION['enroll_secret']);
    $next = after_second_factor(admin_by_id($a['id']));
    out(['backupCodes' => $codes, 'next' => $next]);

case 'verify_2fa':
    $a = require_stage('totp');
    if ((int) $a['locked_until'] > now_ms()) fail('Too many attempts. Try again in a few minutes.');
    $code = trim((string) in('code'));
    if (preg_match('/^[0-9a-f]{4}-[0-9a-f]{4}$/i', $code)) {
        if (!use_backup_code($a, $code)) { note_failure($a); fail('That backup code isn\'t valid (each one works only once).'); }
    } else {
        $step = totp_verify($a['totp_secret'], $code, (int) $a['totp_last_step']);
        if ($step === false) { note_failure($a); fail('That code didn\'t work. Use the newest 6-digit code from your authenticator app.'); }
        qexec('UPDATE admins SET totp_last_step = ? WHERE id = ?', [$step, $a['id']]);
    }
    out(['next' => after_second_factor(admin_by_id($a['id']))]);

case 'set_new_password':
    $a = require_stage('change_pw');
    $pw = (string) in('password');
    if ($pw !== (string) in('password2')) fail('The two passwords don\'t match.');
    validate_password($pw);
    if (password_verify($pw, $a['password_hash'])) fail('Please choose a password different from the temporary one.');
    qexec('UPDATE admins SET password_hash = ?, must_change_pw = 0 WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $a['id']]);
    out(['next' => after_second_factor(admin_by_id($a['id']))]);

case 'cancel_login':
    unset($_SESSION['pending_id'], $_SESSION['pending_stage'], $_SESSION['enroll_secret']);
    out([]);
}
fail('Unknown action.');
