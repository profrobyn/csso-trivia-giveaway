<?php
/* Admin-only API actions. Included from api.php after the login + CSRF check. */
if (!defined('DB_PATH')) exit;

function admin_data() {
    $c = ctx(true); $cfg = get_config(); $bank = active_bank(true);
    $eid = $c['eventId']; $sid = $c['sessionId'];
    $now = now_ms(); $win = $cfg['activeWindowSec'] * 1000;
    $players = qall("SELECT p.id, p.name, p.emoji, p.rejoin_code AS code, p.last_seen, p.created_at,
            COALESCE((SELECT SUM(points) FROM responses r WHERE r.player_id = p.id AND r.scored = 1), 0) AS total,
            COALESCE((SELECT SUM(points) FROM responses r WHERE r.player_id = p.id AND r.scored = 1 AND r.session_id = ?), 0) AS today,
            COALESCE((SELECT COUNT(*) FROM responses r WHERE r.player_id = p.id AND r.scored = 1 AND r.correct = 1), 0) AS correct,
            COALESCE((SELECT COUNT(*) FROM draws d WHERE d.player_id = p.id), 0) AS wins
        FROM players p WHERE p.event_id = ? ORDER BY total DESC, correct DESC, p.id", [$sid, $eid]);
    foreach ($players as &$p) {
        foreach (['id', 'total', 'today', 'correct', 'wins'] as $k) $p[$k] = (int) $p[$k];
        $p['online'] = (int) $p['last_seen'] >= $now - $win;
    }
    unset($p);
    $draws = qall("SELECT d.*, s.label AS session_label, b.name AS bank_name FROM draws d LEFT JOIN sessions s ON s.id = d.session_id
                   LEFT JOIN banks b ON b.id = d.bank_id WHERE d.event_id = ? ORDER BY d.id DESC", [$eid]);
    $draws = array_map(function ($d) { $x = draw_public($d); $x['session'] = $d['session_label']; $x['bank'] = $d['bank_name']; unset($x['candidates']); return $x; }, $draws);
    $events = qall('SELECT e.id, e.name, e.created_at, (SELECT COUNT(*) FROM players p WHERE p.event_id = e.id) AS players,
                    (SELECT COUNT(*) FROM sessions s WHERE s.event_id = e.id) AS sessions FROM events e WHERE e.admin_id = ? ORDER BY e.id DESC', [owner()]);
    $sessions = qall('SELECT s.id, s.label, s.started_at, (SELECT COUNT(DISTINCT round_id) FROM responses r WHERE r.session_id = s.id) AS played
                      FROM sessions s WHERE s.event_id = ? ORDER BY s.id', [$eid]);
    $auto = join_url(array_merge($cfg, ['joinUrl' => '']));
    $joinUrl = join_url($cfg);
    $me = admin_by_id(owner());
    $folderOk = ensure_host_folder($me['handle']);
    $st = get_state();
    return [
        'me' => account_public($me), 'user' => account_public($GLOBALS['RQ_USER']), 'plan' => plan_usage(), 'plans' => PLANS,
        'brand' => brand_public($cfg), 'orgMember' => is_org_member($me), 'orgName' => is_org_member($me) ? org_root($me)['display_name'] . ($me['org'] ? ' · ' . $me['org'] : '') : null, 'launched' => !empty($st['launched']), 'phase' => $st['phase'], 'qIndex' => (int) $st['qIndex'],
        'joinUrl' => $joinUrl, 'joinIsLocal' => is_local_url($joinUrl), 'gameCode' => strtoupper($me['slug']),
        'longJoinUrl' => long_join_url($cfg), 'folderOk' => $folderOk, 'handle' => $me['handle'],
        'questions' => $bank['qs'], 'bank' => ['id' => $bank['id'], 'name' => $bank['name']], 'banks' => banks_list(), 'config' => $cfg, 'event' => $c['event'], 'session' => $c['session'],
        'events' => $events, 'sessions' => $sessions, 'players' => $players, 'draws' => $draws,
        'autoJoinUrl' => $auto, 'autoJoinIsLocal' => is_local_url($auto),
        'sounds' => scan_sounds(), 'soundsChosen' => chosen_sounds($cfg), 'qtypes' => QTYPES,
        'emojis' => EMOJIS,
        'openInvoices' => array_map(function ($i) { return ['number' => $i['number'], 'plan' => $i['plan'], 'amount' => money($i['amount_cents']), 'url' => invoice_url($i)]; },
            qall("SELECT * FROM invoices WHERE admin_id = ? AND status = 'unpaid' ORDER BY id DESC", [org_root($me)['id'] ?? $me['id']])),
        'canUpgrade' => !is_master($me) && empty($me['team_of']),
        'blockedNames' => array_map(function ($b) { return ['name' => $b['name'], 'key' => $b['key']]; }, blocked_names()),
    ];
}

function account_public($a) {
    return [
        'id' => (int) $a['id'], 'username' => $a['username'], 'name' => $a['display_name'], 'org' => $a['org'],
        'phone' => format_phone($a['phone']), 'role' => $a['role'], 'isMaster' => is_master($a),
        'gameCode' => strtoupper($a['slug']), 'status' => $a['status'], 'handle' => $a['handle'],
        'plan' => !empty($a['team_of']) ? null : plan_key($a), 'teamOf' => !empty($a['team_of']) ? (int) $a['team_of'] : null,
        'expiresAt' => $a['expires_at'] ? (int) $a['expires_at'] : null,
        'backupCodesLeft' => count(json_decode($a['backup_codes'] ?: '[]', true) ?: []),
        'lastLogin' => $a['last_login'] ? (int) $a['last_login'] : null,
        'email' => $a['email'] ?? null, 'planUntil' => !empty($a['plan_until']) ? (int) $a['plan_until'] : null,
        'planExpired' => plan_expired($a), 'savedPlan' => $a['plan'] ?? null, 'source' => $a['source'] ?? 'master',
    ];
}

function master_row($a) {
    $id = (int) $a['id'];
    $x = account_public($a);
    $x['notes'] = $a['notes'];
    $x['joinPath'] = $a['handle'] . '/';
    $x['teamOfName'] = !empty($a['team_of']) ? (string) qval('SELECT display_name FROM admins WHERE id = ?', [$a['team_of']]) : null;
    $x['teamSize'] = (int) qval('SELECT COUNT(*) FROM admins WHERE team_of = ?', [$id]);
    $x['createdAt'] = (int) $a['created_at'];
    $x['twoFactor'] = (bool) $a['totp_enabled'];
    $x['mustChange'] = (bool) $a['must_change_pw'];
    $x['expired'] = $a['expires_at'] && (int) $a['expires_at'] < now_ms();
    $x['events'] = (int) qval('SELECT COUNT(*) FROM events WHERE admin_id = ?', [$id]);
    $x['players'] = (int) qval('SELECT COUNT(*) FROM players p JOIN events e ON e.id = p.event_id WHERE e.admin_id = ?', [$id]);
    $x['banks'] = (int) qval('SELECT COUNT(*) FROM banks WHERE admin_id = ?', [$id]);
    $x['openInvoices'] = (int) qval("SELECT COUNT(*) FROM invoices WHERE admin_id = ? AND status = 'unpaid'", [$id]);
    return $x;
}

function parse_expiry($v) {
    $v = trim((string) $v);
    if ($v === '') return null;
    $t = strtotime($v . ' 23:59:59');
    if (!$t) fail('Please pick a valid expiration date.');
    return $t * 1000;
}

function activate_bank($id) {
    $b = bank_get($id);
    $s = get_state();
    if ($s['phase'] === 'QUESTION') fail('A question is running. Wait for it to end before switching question banks.');
    setting_set('active_bank_id', (int) $b['id']);
    qexec('UPDATE banks SET updated_at = ? WHERE id = ?', [now_ms(), $b['id']]);
    active_bank(true);
    reset_to_lobby(count($b['qs']));
}

function csv_out($filename, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
    foreach ($rows as $r) fputcsv($f, $r);
    fclose($f);
    exit;
}

$modeNames = ['random_correct' => 'Random correct answer', 'random_player' => 'Random player', 'fastest' => 'Fastest correct answer'];

switch ($action) {
case 'logout':
    $_SESSION = []; session_destroy();
    out([]);

case 'admin_data':
    out(admin_data());

/* ---------------- results for Excel (built in the browser) ---------------- */
case 'export_data':
    require_feature('export', 'Downloading results');
    $c = ctx(); $eid = $c['eventId'];
    $all = in('scope', 'event') !== 'session';
    $where = $all ? 'event_id = ?' : 'session_id = ?';
    $arg = $all ? $eid : $c['sessionId'];
    $sessions = [];
    foreach (qall('SELECT id, label FROM sessions WHERE event_id = ?', [$eid]) as $s) $sessions[(int) $s['id']] = $s['label'];
    $banks = [];
    foreach (qall('SELECT id, name FROM banks WHERE admin_id = ?', [owner()]) as $b) $banks[(int) $b['id']] = $b['name'];
    $rounds = qall("SELECT * FROM rounds WHERE $where ORDER BY started_at", [$arg]);
    $roundOut = []; $answers = []; $ideas = []; $words = [];
    $names = [];
    foreach (qall('SELECT id, name, emoji FROM players WHERE event_id = ?', [$eid]) as $p) $names[(int) $p['id']] = $p;
    foreach ($rounds as $i => $r) {
        $q = json_decode($r['q'], true) ?: [];
        $label = 'Q' . ((int) $r['q_index'] + 1) . ($q['name'] ?? '' ? ' (' . $q['name'] . ')' : '');
        $resp = qall('SELECT * FROM responses WHERE round_id = ? ORDER BY elapsed_ms', [$r['id']]);
        $nCorrect = 0;
        foreach ($resp as $x) {
            if ((int) $x['correct']) $nCorrect++;
            $p = $names[(int) $x['player_id']] ?? ['name' => '(removed)', 'emoji' => ''];
            $answers[] = [
                'Day' => $sessions[(int) $r['session_id']] ?? '', 'Bank' => $banks[(int) $r['bank_id']] ?? '', 'Question #' => $label,
                'Question' => $q['text'] ?? '', 'Type' => QTYPES[q_type($q)]['label'], 'Player' => $p['name'],
                'Answer' => answer_text($q, $x['choice'] === null ? null : (int) $x['choice'], $x['answer']),
                'Correct' => q_scored($q) ? ((int) $x['correct'] ? 'Yes' : 'No') : '—',
                'Seconds' => round((int) $x['elapsed_ms'] / 1000, 2), 'Points' => (int) $x['points'],
            ];
        }
        $right = '';
        switch (q_type($q)) {
            case 'mc': case 'tf': $right = $q['options'][$q['correctIndex']] ?? ''; break;
            case 'typeans': $right = implode(' / ', $q['accepted'] ?? []); break;
            case 'estimate': $right = ($q['answer'] ?? '') . ' ' . ($q['unit'] ?? '') . (($q['tolerance'] ?? 0) ? ' (±' . $q['tolerance'] . ')' : ''); break;
            case 'ranking': $right = implode(' > ', $q['items'] ?? []); break;
        }
        $roundOut[] = [
            'Day' => $sessions[(int) $r['session_id']] ?? '', 'Bank' => $banks[(int) $r['bank_id']] ?? '', 'Question #' => $label,
            'Question' => $q['text'] ?? '', 'Type' => QTYPES[q_type($q)]['label'], 'Correct answer' => $right,
            'Responses' => count($resp), 'Correct' => q_scored($q) ? $nCorrect : '—',
            '% correct' => q_scored($q) && count($resp) ? round($nCorrect / count($resp) * 100) . '%' : '—',
            'Played at' => date('Y-m-d g:i a', (int) ($r['started_at'] / 1000)),
        ];
        if (q_type($q) === 'ideas') {
            foreach (idea_rows($r['id'], $q) as $it) $ideas[] = [
                'Day' => $sessions[(int) $r['session_id']] ?? '', 'Question' => $q['text'] ?? '', 'Idea' => $it['text'], 'Shared by' => $it['name'] ?? '',
                'Nominations ⭐' => $it['up'], 'Thumbs down 👎' => $it['down'], 'Removed' => $it['removed'] ? 'Yes' : 'No'];
        }
        if (q_type($q) === 'wordcloud') {
            $bag = [];
            foreach ($resp as $x) foreach ((array) json_decode($x['answer'] ?? '[]', true) as $w) { $k = mb_strtolower($w); $bag[$k] = ($bag[$k] ?? 0) + 1; }
            arsort($bag);
            foreach ($bag as $w => $n) $words[] = ['Day' => $sessions[(int) $r['session_id']] ?? '', 'Question' => $q['text'] ?? '', 'Word' => $w, 'Count' => $n];
        }
    }
    $d = admin_data();
    $stand = [];
    foreach ($d['players'] as $i => $p) $stand[] = ['Rank' => $i + 1, 'Emoji' => $p['emoji'], 'Name' => $p['name'], 'Event total' => $p['total'],
        'Today (' . $d['session']['label'] . ')' => $p['today'], 'Correct answers' => $p['correct'], 'Prizes won' => $p['wins'], 'Rejoin code' => $p['code']];
    $wins = [];
    foreach (array_reverse($d['draws']) as $w) $wins[] = ['Day' => $w['session'], 'Bank' => $w['bank'] ?? '', 'Question' => $w['qIndex'] >= 0 ? 'Q' . ($w['qIndex'] + 1) . ($w['qName'] ? ' (' . $w['qName'] . ')' : '') : 'Bonus',
        'Winner' => $w['name'] ? trim($w['emoji'] . ' ' . $w['name']) : '(no eligible player)', 'Prize type' => ($w['note'] ?? '') === 'best_idea' ? 'Best idea' : ($modeNames[$w['mode']] ?? $w['mode']),
        'Seconds' => $w['ms'] !== null ? round($w['ms'] / 1000, 2) : '', 'Drawn' => $w['auto'] ? 'Auto' : 'Host', 'Time' => date('Y-m-d g:i a', (int) ($w['at'] / 1000))];
    out(['event' => $d['event']['name'], 'session' => $d['session']['label'], 'title' => $d['config']['title'],
         'sheets' => ['Standings' => $stand, 'Questions' => $roundOut, 'Answers' => $answers, 'Prize winners' => $wins, 'Ideas' => $ideas, 'Word clouds' => $words]]);

/* ---------------- launch / close ---------------- */
case 'launch':
    if (!count(get_questions())) fail('Add or load some questions before launching.');
    $s = get_state();
    if (empty($s['launched'])) {
        $p = plan(); $sid = ctx()['sessionId'];
        $already = q1('SELECT id FROM launches WHERE admin_id = ? AND session_id = ? AND created_at >= ?', [owner(), $sid, month_start_ms()]);
        if (!$already && $p['quizzes'] && quizzes_this_month() >= $p['quizzes'])
            fail('Your ' . $p['label'] . ' plan includes ' . $p['quizzes'] . ' quizzes a month, and you\'ve used them all. Ask your RizQuiz administrator about upgrading.');
        if (!$already) qexec('INSERT INTO launches (admin_id, session_id, created_at) VALUES (?,?,?)', [owner(), $sid, now_ms()]);
        $s = array_merge(default_state(), ['launched' => true, 'launchedAt' => now_ms(), 'totalQ' => count(get_questions())]);
        set_state($s);
    }
    out(['ok' => true]);

case 'close_game':
    $s = get_state();
    $s = array_merge(default_state(), ['launched' => false, 'qIndex' => -1, 'totalQ' => count(get_questions())]);
    set_state($s);
    out([]);

case 'make_short_url':
    $long = long_join_url(get_config());
    if (is_local_url($long)) fail('Short links can\'t point to “localhost”. First set the join web address in Game settings (your computer\'s network address or website), then try again.');
    $res = trim(http_get('https://is.gd/create.php?format=simple&url=' . rawurlencode($long)));
    if (!preg_match('#^https?://\S+$#', $res)) fail('The short-link service said: ' . mb_substr_safe(strip_tags($res), 160) . ' — you can paste a link from bit.ly or tinyurl.com instead.');
    $cfg = save_config(['shortUrl' => $res, 'useShortUrl' => true]);
    out(['shortUrl' => $res, 'config' => $cfg]);

/* ---------------- my account ---------------- */
case 'account_info':
    out(['me' => account_public(admin_by_id($me["id"]))]);

case 'change_password':
    $a = admin_by_id($me["id"]);
    if (!password_verify((string) in('current'), $a['password_hash'])) fail('Your current password is not correct.');
    if ((string) in('password') !== (string) in('password2')) fail('The two new passwords don\'t match.');
    validate_password((string) in('password'));
    qexec('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash((string) in('password'), PASSWORD_DEFAULT), $a['id']]);
    out([]);

case 'new_backup_codes':
    $a = admin_by_id($me["id"]);
    $step = totp_verify($a['totp_secret'], in('code'), (int) $a['totp_last_step']);
    if ($step === false) fail('Enter the current 6-digit code from your authenticator app to confirm.');
    $codes = new_backup_codes();
    qexec('UPDATE admins SET backup_codes = ?, totp_last_step = ? WHERE id = ?',
        [json_encode(array_map(function ($c) { return password_hash($c, PASSWORD_DEFAULT); }, $codes)), $step, $a['id']]);
    out(['backupCodes' => $codes]);

/* ---------------- plans: upgrade request (any host) ---------------- */
case 'request_upgrade':
    $a = admin_by_id($me['id']);
    if (is_master($a) || !empty($a['team_of'])) fail('Your plan is managed by your organization.');
    $plan = (string) in('plan');
    if (!in_array($plan, ['basic', 'pro'], true)) fail('Choose Basic or Pro.');
    if (!$a['email']) {
        $email = clean_email(in('email', $a['username']));
        qexec('UPDATE admins SET email = ? WHERE id = ?', [$email, $a['id']]);
        $a['email'] = $email;
    }
    $inv = invoice_create($a, $plan);
    $sent = invoice_email($inv);
    notify_owner('Upgrade request: ' . $a['display_name'] . ' → ' . PLANS[$plan]['label'] . ' (' . $inv['number'] . ')',
        "{$a['display_name']} ({$a['email']}) asked to upgrade to " . PLANS[$plan]['label'] . ". Invoice {$inv['number']} for " . money($inv['amount_cents'])
        . ($sent === true ? ' was emailed.' : " could NOT be emailed: $sent"));
    out(['invoice' => $inv['number'], 'invoiceUrl' => invoice_url($inv), 'amount' => money($inv['amount_cents']), 'emailed' => $sent === true, 'email' => $inv['email']]);

/* ---------------- master: billing, invoices, sales requests ---------------- */
case 'master_billing':
    $inv = qall('SELECT i.*, a.handle, a.plan AS cur_plan, a.plan_until FROM invoices i LEFT JOIN admins a ON a.id = i.admin_id ORDER BY i.id DESC LIMIT 500');
    out(['billing' => billing_public(), 'mailReady' => mail_ready(),
        'invoices' => array_map(function ($i) { return ['id' => (int) $i['id'], 'number' => $i['number'], 'plan' => $i['plan'], 'amount' => money($i['amount_cents']),
            'status' => $i['status'], 'name' => $i['name'], 'org' => $i['org'], 'email' => $i['email'], 'adminId' => (int) $i['admin_id'], 'handle' => $i['handle'],
            'created' => (int) $i['created_at'], 'due' => (int) $i['due_at'], 'paid' => $i['paid_at'] ? (int) $i['paid_at'] : null, 'sent' => $i['sent_at'] ? (int) $i['sent_at'] : null,
            'until' => $i['period_end'] ? (int) $i['period_end'] : null, 'url' => invoice_url($i)]; }, $inv),
        'leads' => array_map(function ($l) { return ['id' => (int) $l['id'], 'name' => $l['name'], 'org' => $l['org'], 'email' => $l['email'], 'phone' => $l['phone'],
            'size' => $l['size'], 'times' => $l['times'], 'message' => $l['message'], 'status' => $l['status'], 'created' => (int) $l['created_at']]; },
            qall('SELECT * FROM leads ORDER BY id DESC LIMIT 500')),
        'mail' => array_map(function ($m) { return ['to' => $m['to_addr'], 'subject' => $m['subject'], 'ok' => (bool) $m['ok'], 'error' => $m['error'], 'at' => (int) $m['created_at']]; },
            qall('SELECT * FROM mail_log ORDER BY id DESC LIMIT 30')),
        'signupUrl' => app_base_url() . 'signup.php']);

case 'master_billing_save':
    $b = billing_save(in_json('billing'));
    out(['billing' => billing_public($b), 'mailReady' => mail_ready($b)]);

case 'master_test_mail':
    $to = clean_email(in('to'));
    $r = send_mail($to, APP_NAME . ' test email', "This is a test from " . APP_NAME . ". If you can read it, invoices and welcome emails will be delivered.",
        mail_html('Test email', '<p>This is a test from <b>' . h(APP_NAME) . '</b>.</p><p>If you can read it, invoices and welcome emails will be delivered. 🎉</p>'));
    if ($r !== true) fail('The test email was not sent: ' . $r);
    out([]);

case 'master_invoice_paid': case 'master_invoice_void': case 'master_invoice_resend':
    $inv = q1('SELECT * FROM invoices WHERE id = ?', [(int) in('id')]);
    if (!$inv) fail('Invoice not found.');
    if ($action === 'master_invoice_paid') {
        if ($inv['status'] === 'void') fail('This invoice was cancelled.');
        $inv = invoice_mark_paid($inv);
        $a = admin_by_id($inv['admin_id']);
        $label = PLANS[$inv['plan']]['label']; $until = date('F j, Y', (int) ($inv['period_end'] / 1000));
        $sent = send_mail($inv['email'], 'Payment received — ' . APP_NAME . ' ' . $label . ' is active',
            "Hi {$inv['name']},\n\nThank you! We received your payment for invoice {$inv['number']} (" . money($inv['amount_cents']) . ").\n\n"
            . "Your " . APP_NAME . " {$label} plan is now active until {$until}.\n\nSign in: " . app_base_url() . "login.php\n\n— " . billing_get()['businessName'],
            mail_html('Payment received', '<p>Hi ' . h($inv['name']) . ',</p><p>Thank you! We received your payment for invoice <b>' . h($inv['number']) . '</b> (' . money($inv['amount_cents']) . ').</p>'
                . '<p>Your <b>' . h($label) . '</b> plan is now active until <b>' . h($until) . '</b>.</p><p><a href="' . h(invoice_url($inv)) . '">View your paid invoice</a></p>'));
        out(['emailed' => $sent === true, 'error' => $sent === true ? null : $sent, 'until' => (int) $inv['period_end'], 'account' => $a ? $a['display_name'] : null]);
    }
    if ($action === 'master_invoice_void') {
        if ($inv['status'] === 'paid') fail('This invoice is already paid. Change the account\'s plan on the Accounts page instead.');
        qexec("UPDATE invoices SET status = 'void' WHERE id = ?", [$inv['id']]);
        out([]);
    }
    $r = invoice_email($inv);
    if ($r !== true) fail('Not sent: ' . $r);
    out([]);

case 'master_invoice_new':
    $a = admin_by_id(in('adminId'));
    if (!$a || is_master($a) || !empty($a['team_of'])) fail('Choose a host account.');
    if (in('email', '') !== '') { $em = clean_email(in('email')); qexec('UPDATE admins SET email = ? WHERE id = ?', [$em, $a['id']]); $a['email'] = $em; }
    $inv = invoice_create($a, (string) in('plan'));
    $r = in('send') === '1' ? invoice_email($inv) : true;
    out(['invoice' => $inv['number'], 'emailed' => $r === true, 'error' => $r === true ? null : $r]);

case 'master_lead_update':
    $st = (string) in('status');
    if (!in_array($st, ['new', 'contacted', 'won', 'closed'], true)) fail('Unknown status.');
    qexec('UPDATE leads SET status = ? WHERE id = ?', [$st, (int) in('id')]);
    out([]);

/* ---------------- master admin: accounts ---------------- */
case 'master_list':
    out(['admins' => array_map('master_row', qall("SELECT * FROM admins ORDER BY role = 'master' DESC, id"))]);

case 'master_create':
    $id = create_admin(['plan' => in('plan', 'basic'), 'handle' => in('handle'), 'username' => in('username'), 'password' => in('password'), 'display_name' => in('display_name'),
        'org' => in('org'), 'phone' => in('phone'), 'notes' => in('notes'), 'expires_at' => parse_expiry(in('expires'))]);
    out(['admin' => master_row(admin_by_id($id))]);

case 'master_update':
    $a = admin_by_id(in('id'));
    if (!$a) fail('Account not found.');
    $status = in('status', $a['status']) === 'paused' ? 'paused' : 'active';
    if (is_master($a)) $status = 'active';
    $newHandle = in('handle', '') !== '' ? unique_handle(in('handle'), (int) $a['id']) : $a['handle'];
    if ($newHandle !== $a['handle']) {
        qexec('UPDATE admins SET handle = ? WHERE id = ?', [$newHandle, $a['id']]);
        ensure_host_folder($newHandle);
        remove_host_folder($a['handle']);
    }
    $planIn = in('plan', '');
    if (!is_master($a) && empty($a['team_of']) && isset(PLANS[$planIn]) && $planIn !== $a['plan']) qexec('UPDATE admins SET plan = ?, plan_until = NULL WHERE id = ?', [$planIn, $a['id']]);   // a plan set by hand doesn't run out
    qexec('UPDATE admins SET display_name = ?, org = ?, phone = ?, notes = ?, status = ?, expires_at = ? WHERE id = ?', [
        trim((string) in('display_name', $a['display_name'])) ?: $a['username'], trim((string) in('org', $a['org'])),
        is_master($a) ? MASTER_PHONE : preg_replace('/[^\d+]/', '', (string) in('phone', $a['phone'])),
        trim((string) in('notes', $a['notes'])), $status, is_master($a) ? null : parse_expiry(in('expires')), $a['id']]);
    out(['admin' => master_row(admin_by_id($a['id']))]);

case 'master_reset_password':
    $a = admin_by_id(in('id'));
    if (!$a) fail('Account not found.');
    if (is_master($a)) fail('Change the master password from My Account.');
    validate_password((string) in('password'));
    qexec('UPDATE admins SET password_hash = ?, must_change_pw = 1, failed_logins = 0, locked_until = 0 WHERE id = ?',
        [password_hash((string) in('password'), PASSWORD_DEFAULT), $a['id']]);
    out(['admin' => master_row(admin_by_id($a['id']))]);

case 'master_reset_2fa':
    $a = admin_by_id(in('id'));
    if (!$a) fail('Account not found.');
    if (is_master($a)) fail('The master\'s authenticator can only be reset with MASTER_RESET_2FA in config.php.');
    qexec('UPDATE admins SET totp_enabled = 0, totp_secret = NULL, backup_codes = NULL, totp_last_step = 0 WHERE id = ?', [$a['id']]);
    out(['admin' => master_row(admin_by_id($a['id']))]);

case 'master_new_code':
    $a = admin_by_id(in('id'));
    if (!$a) fail('Account not found.');
    qexec('UPDATE admins SET slug = ? WHERE id = ?', [new_slug(), $a['id']]);
    out(['admin' => master_row(admin_by_id($a['id']))]);

case 'master_delete':
    $a = admin_by_id(in('id'));
    if (!$a) fail('Account not found.');
    if (is_master($a)) fail('The master admin can\'t be deleted.');
    tx(function () use ($a) {
        $id = (int) $a['id'];
        foreach (qall('SELECT id FROM events WHERE admin_id = ?', [$id]) as $e) {
            qexec('DELETE FROM draws WHERE event_id = ?', [$e['id']]);
            qexec('DELETE FROM waves WHERE event_id = ?', [$e['id']]);
            qexec('DELETE FROM responses WHERE event_id = ?', [$e['id']]);
        }
        qexec('DELETE FROM events WHERE admin_id = ?', [$id]);     // sessions + players cascade
        qexec('DELETE FROM banks WHERE admin_id = ?', [$id]);
        qexec("DELETE FROM settings WHERE k LIKE ?", ['a' . $id . ':%']);
        qexec('DELETE FROM admins WHERE id = ?', [$id]);
    });
    remove_host_folder($a['handle']);
    out([]);

case 'save_questions':
    $qs = normalize_questions(in_json('questions'));
    bank_save(active_bank()['id'], $qs);
    $s = get_state();
    if ($s['phase'] !== 'QUESTION') { $s['totalQ'] = count($qs); set_state($s); }
    out(['questions' => $qs, 'banks' => banks_list()]);

/* ---------------- question banks ---------------- */
case 'bank_create':      // new bank from pasted/uploaded JSON (or empty); optionally make it active
    $qs = normalize_questions(in_json('questions'));
    $id = bank_create(in('name'), $qs);
    if (in('activate', '1') === '1') activate_bank($id);
    out(admin_data());

case 'bank_activate':
    activate_bank((int) in('id'));
    out(admin_data());

case 'bank_rename':
    $b = bank_get(in('id'));
    $name = trim((string) in('name'));
    if ($name === '') fail('Please type a name.');
    qexec('UPDATE banks SET name = ? WHERE id = ?', [mb_substr_safe($name, 80), $b['id']]);
    out(admin_data());

case 'bank_duplicate':
    $b = bank_get(in('id'));
    bank_create($b['name'] . ' (copy)', $b['qs']);
    out(admin_data());

case 'bank_delete':
    $b = bank_get(in('id'));
    if ((int) $b['id'] === active_bank()['id']) fail('You can\'t delete the bank that is currently loaded. Load a different bank first.');
    qexec('DELETE FROM banks WHERE id = ?', [$b['id']]);
    out(admin_data());

case 'bank_append':      // copy another bank's questions onto the end of the loaded bank
    $src = bank_get(in('id'));
    $cur = active_bank();
    if ((int) $src['id'] === $cur['id']) fail('That bank is already loaded.');
    $qs = array_merge($cur['qs'], array_map(function ($q) { $q['id'] = 'q_' . bin2hex(random_bytes(4)); return $q; }, $src['qs']));
    bank_save($cur['id'], $qs);
    $s = get_state();
    if ($s['phase'] !== 'QUESTION') { $s['totalQ'] = count($qs); set_state($s); }
    out(admin_data());

case 'bank_view':
    $b = bank_get(in('id'));
    out(['bank' => ['id' => (int) $b['id'], 'name' => $b['name'], 'questions' => $b['qs']]]);

case 'save_config':
    $in = in_json('config');
    if (!plan()['branding'] || is_org_member()) foreach (['brandPrimary', 'brandAccent', 'brandBg', 'brandLogo'] as $k) unset($in[$k]);
    out(['config' => save_config($in)]);

/* ---------------- branding (Pro) ---------------- */
case 'brand_logo':
    require_feature('branding', 'Custom branding');
    if (is_org_member()) fail('Your organization\'s master user manages the official branding.');
    $f = $_FILES['logo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) fail('Please choose an image file.');
    if ($f['size'] > 1024 * 1024) fail('Please use an image under 1 MB.');
    $info = @getimagesize($f['tmp_name']);
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) fail('Logos must be PNG, JPG, GIF or WebP images.');
    $dir = __DIR__ . '/branding';
    if (!is_dir($dir) && !@mkdir($dir, 0775)) fail('Could not create the branding folder. Check that PHP can write to the RizQuiz folder.');
    $cfg = get_config();
    $name = 'logo-' . owner() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) fail('Could not save the logo.');
    if ($cfg['brandLogo'] && strpos($cfg['brandLogo'], 'branding/logo-' . owner() . '-') === 0) @unlink(__DIR__ . '/' . $cfg['brandLogo']);
    out(['config' => save_config(['brandLogo' => 'branding/' . $name])]);

case 'brand_logo_remove':
    $cfg = get_config();
    if ($cfg['brandLogo'] && strpos($cfg['brandLogo'], 'branding/logo-' . owner() . '-') === 0) @unlink(__DIR__ . '/' . $cfg['brandLogo']);
    out(['config' => save_config(['brandLogo' => ''])]);

/* ---------------- enterprise organization: master user manages member accounts ---------------- */
case 'team_list':
    if (is_org_member()) fail('Only your organization\'s master user can manage users.');
    out(['team' => array_map('master_row', qall('SELECT * FROM admins WHERE team_of = ? ORDER BY id', [owner()]))]);

case 'team_add':
    require_feature('team', 'Adding organization users');
    if (is_org_member()) fail('Only your organization\'s master user can add users.');
    $id = create_admin(['username' => in('username'), 'password' => in('password'), 'display_name' => in('display_name'),
        'handle' => in('handle'), 'org' => owner_account()['org'], 'team_of' => owner(), 'plan' => 'enterprise']);
    out(['admin' => master_row(admin_by_id($id))]);

case 'team_update': case 'team_remove': case 'team_reset_password': case 'team_reset_2fa':
    if (is_org_member()) fail('Only your organization\'s master user can manage users.');
    $t = q1('SELECT * FROM admins WHERE id = ? AND team_of = ?', [(int) in('id'), owner()]);
    if (!$t) fail('User not found in your organization.');
    if ($action === 'team_update') qexec('UPDATE admins SET status = ?, display_name = ? WHERE id = ?',
        [in('status') === 'paused' ? 'paused' : 'active', trim((string) in('display_name', $t['display_name'])) ?: $t['username'], $t['id']]);
    if ($action === 'team_reset_password') { validate_password((string) in('password'));
        qexec('UPDATE admins SET password_hash = ?, must_change_pw = 1, failed_logins = 0, locked_until = 0 WHERE id = ?', [password_hash((string) in('password'), PASSWORD_DEFAULT), $t['id']]); }
    if ($action === 'team_reset_2fa') qexec('UPDATE admins SET totp_enabled = 0, totp_secret = NULL, backup_codes = NULL, totp_last_step = 0 WHERE id = ?', [$t['id']]);
    if ($action === 'team_remove') {
        set_owner($t['id']);
        foreach (qall('SELECT id FROM events WHERE admin_id = ?', [$t['id']]) as $e) {
            qexec('DELETE FROM draws WHERE event_id = ?', [$e['id']]); qexec('DELETE FROM waves WHERE event_id = ?', [$e['id']]); qexec('DELETE FROM responses WHERE event_id = ?', [$e['id']]);
        }
        qexec('DELETE FROM events WHERE admin_id = ?', [$t['id']]); qexec('DELETE FROM banks WHERE admin_id = ?', [$t['id']]);
        qexec('DELETE FROM settings WHERE k LIKE ?', ['a' . $t['id'] . ':%']); qexec('DELETE FROM admins WHERE id = ?', [$t['id']]);
        remove_host_folder($t['handle']);
        out([]);
    }
    out(['admin' => master_row(admin_by_id($t['id']))]);


case 'start_question':
    $idx = (int) in('index');
    start_question($idx, in('bankId') !== '' ? (int) in('bankId') : null);
    out([]);

case 'question_search':  // find questions by name (or text) across every bank
    $term = function_exists('mb_strtolower') ? mb_strtolower(trim((string) in('q')), 'UTF-8') : strtolower(trim((string) in('q')));
    $lc = function ($x) { return function_exists('mb_strtolower') ? mb_strtolower($x, 'UTF-8') : strtolower($x); };
    $active = active_bank()['id'];
    $hits = [];
    foreach (qall('SELECT id, name, questions FROM banks WHERE admin_id = ? ORDER BY id = ' . (int) $active . ' DESC, updated_at DESC', [owner()]) as $b) {
        foreach (json_decode($b['questions'] ?: '[]', true) ?: [] as $i => $q) {
            $name = $q['name'] ?? '';
            $inName = $term !== '' && strpos($lc($name), $term) !== false;
            $inText = $term !== '' && strpos($lc($q['text']), $term) !== false;
            if ($term === '' ? $name === '' : !($inName || $inText)) continue;
            $hits[] = ['bankId' => (int) $b['id'], 'bankName' => $b['name'], 'loaded' => (int) $b['id'] === $active,
                'index' => $i, 'name' => $name, 'text' => $q['text'], 'byName' => $inName || $term === ''];
        }
    }
    usort($hits, function ($a, $b) { return [$b['byName'], $b['loaded']] <=> [$a['byName'], $a['loaded']]; });
    out(['hits' => array_slice($hits, 0, 30)]);

case 'end_question':
    advance_question();
    out([]);

case 'draw':
    $id = do_draw((string) in('mode'));
    $row = q1('SELECT * FROM draws WHERE id = ?', [$id]);
    out(['draw' => draw_public($row)]);

case 'delete_draw':
    qexec('DELETE FROM draws WHERE id = ? AND event_id = ?', [(int) in('id'), ctx()['eventId']]);
    out(admin_data());

case 'set_phase':
    $ph = (string) in('phase');
    if (!in_array($ph, ['LOBBY', 'LEADERBOARD', 'PODIUM', 'REVEAL'], true)) fail('Bad phase.');
    set_phase($ph);
    out([]);

case 'new_session':
    $c = ctx();
    $label = trim((string) in('label')) ?: ('Day ' . ((int) qval('SELECT COUNT(*) FROM sessions WHERE event_id = ?', [$c['eventId']]) + 1));
    $s = create_session($c['eventId'], $label);
    setting_set('current_session_id', (int) $s['id']);
    reset_to_lobby();
    out(admin_data());

case 'rename_session':
    qexec('UPDATE sessions SET label = ? WHERE id = ? AND event_id = ?', [trim((string) in('label')) ?: 'Session', (int) in('id'), ctx()['eventId']]);
    out(admin_data());

case 'new_event':
    $name = trim((string) in('name')) ?: 'New Event';
    tx(function () use ($name) { create_event($name); });
    reset_to_lobby();
    out(admin_data());

case 'switch_event':
    $id = (int) in('id');
    if (!q1('SELECT id FROM events WHERE id = ? AND admin_id = ?', [$id, owner()])) fail('Event not found.');
    setting_set('current_event_id', $id);
    $s = q1('SELECT id FROM sessions WHERE event_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    setting_set('current_session_id', $s ? (int) $s['id'] : 0);
    reset_to_lobby();
    out(admin_data());

case 'rename_event':
    qexec('UPDATE events SET name = ? WHERE id = ?', [trim((string) in('name')) ?: 'Event', ctx()['eventId']]);
    out(admin_data());

case 'player_update':
    $c = ctx(); $id = (int) in('id');
    $p = q1('SELECT * FROM players WHERE id = ? AND event_id = ?', [$id, $c['eventId']]);
    if (!$p) fail('Player not found.');
    $name = clean_name(in('name', $p['name']));
    $dupe = q1('SELECT id FROM players WHERE event_id = ? AND name_key = ? AND id <> ?', [$c['eventId'], name_key($name), $id]);
    if ($dupe) fail('Another player already uses that name.');
    qexec('UPDATE players SET name = ?, name_key = ?, emoji = ? WHERE id = ?', [$name, name_key($name), clean_emoji(in('emoji', $p['emoji'])), $id]);
    out(admin_data());

case 'player_delete':
    // Removes one player and everything they added: answers, word-cloud words, ideas, votes and prize wins.
    $c = ctx();
    $p = q1('SELECT * FROM players WHERE id = ? AND event_id = ?', [(int) in('id'), $c['eventId']]);
    if (!$p) fail('That player is already gone.');
    tx(function () use ($p, $c) {
        qexec('DELETE FROM draws WHERE player_id = ? AND event_id = ?', [$p['id'], $c['eventId']]);
        qexec('DELETE FROM idea_votes WHERE idea_id IN (SELECT id FROM ideas WHERE player_id = ?)', [$p['id']]);
        qexec('DELETE FROM ideas WHERE player_id = ?', [$p['id']]);
        qexec('DELETE FROM idea_votes WHERE player_id = ?', [$p['id']]);
        qexec('DELETE FROM responses WHERE player_id = ?', [$p['id']]);
        qexec('DELETE FROM waves WHERE event_id = ? AND name = ?', [$c['eventId'], $p['name']]);
        qexec('DELETE FROM players WHERE id = ?', [$p['id']]);
        if (in('block') === '1' && !name_blocked($p['name'])) {
            $list = blocked_names();
            $list[] = ['name' => $p['name'], 'key' => block_key($p['name']), 'at' => now_ms()];
            setting_set('blockedNames', $list);
        }
    });
    out(admin_data());

case 'unblock_name':
    $k = (string) in('key');
    setting_set('blockedNames', array_values(array_filter(blocked_names(), function ($b) use ($k) { return ($b['key'] ?? '') !== $k; })));
    out(admin_data());

case 'reset_scores':
    $c = ctx();
    tx(function () use ($c) {
        qexec('DELETE FROM responses WHERE event_id = ?', [$c['eventId']]);
        qexec('DELETE FROM draws WHERE event_id = ?', [$c['eventId']]);
        reset_to_lobby();
    });
    out(admin_data());

case 'remove_players':
case 'reset_game':
    // Wipes every player of this event (all days) and everything they did. Questions, settings and branding stay.
    if (strtoupper(trim((string) in('confirm', ''))) !== 'RESET') fail('Type RESET to confirm.');
    $c = ctx();
    tx(function () use ($c) {
        $e = $c['eventId'];
        qexec('DELETE FROM idea_votes WHERE idea_id IN (SELECT id FROM ideas WHERE event_id = ?)', [$e]);
        qexec('DELETE FROM ideas WHERE event_id = ?', [$e]);
        qexec('DELETE FROM responses WHERE event_id = ?', [$e]);
        qexec('DELETE FROM draws WHERE event_id = ?', [$e]);
        qexec('DELETE FROM waves WHERE event_id = ?', [$e]);
        qexec('DELETE FROM rounds WHERE event_id = ?', [$e]);
        qexec('DELETE FROM players WHERE event_id = ?', [$e]);
        reset_to_lobby();
    });
    out(admin_data());

case 'export_csv':
    require_feature('export', 'Downloading results');
    $d = admin_data();
    $rows = [['Rank', 'Emoji', 'Name', 'Event Total', 'Today (' . $d['session']['label'] . ')', 'Correct Answers', 'Prizes Won', 'Rejoin Code']];
    foreach ($d['players'] as $i => $p) $rows[] = [$i + 1, $p['emoji'], $p['name'], $p['total'], $p['today'], $p['correct'], $p['wins'], $p['code']];
    csv_out('rizquiz_standings_' . date('Y-m-d') . '.csv', $rows);

case 'export_winners':
    require_feature('export', 'Downloading results');
    $d = admin_data();
    $rows = [['Session', 'Question Bank', 'Question', 'Winner', 'Prize Type', 'Answer Time (s)', 'Drawn', 'Time']];
    foreach (array_reverse($d['draws']) as $w) $rows[] = [$w['session'], $w['bank'] ?? '', $w['qIndex'] >= 0 ? 'Q' . ($w['qIndex'] + 1) . ($w['qName'] ? ' (' . $w['qName'] . ')' : '') : '-',
        $w['name'] ? trim($w['emoji'] . ' ' . $w['name']) : '(no eligible player)', $modeNames[$w['mode']] ?? $w['mode'],
        $w['ms'] !== null ? round($w['ms'] / 1000, 2) : '', $w['auto'] ? 'Auto' : 'Host', date('Y-m-d g:i a', (int) ($w['at'] / 1000))];
    csv_out('rizquiz_winners_' . date('Y-m-d') . '.csv', $rows);
}
fail('Unknown action.');
