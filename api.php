<?php
/* RizQuiz JSON API — used by the player page, projector, setup/live pages, login and master admin. */
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function out($data) { echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE); exit; }
function fail($msg, $code = null) { echo json_encode(['ok' => false, 'error' => $msg, 'code' => $code], JSON_UNESCAPED_UNICODE); exit; }
function in($k, $def = '') { return $_POST[$k] ?? $_GET[$k] ?? $def; }
function in_json($k) { $v = json_decode((string) in($k, ''), true); return is_array($v) ? $v : []; }

$action = (string) ($_GET['action'] ?? '');

$adminActions = ['admin_data', 'save_questions', 'save_config', 'start_question', 'end_question', 'draw', 'set_phase',
    'new_session', 'new_event', 'switch_event', 'rename_event', 'rename_session', 'player_update', 'player_delete', 'unblock_name',
    'reset_scores', 'remove_players', 'reset_game', 'delete_draw', 'export_csv', 'export_winners', 'logout',
    'question_search', 'bank_create', 'bank_activate', 'bank_rename', 'bank_duplicate', 'bank_delete', 'bank_append', 'bank_view',
    'launch', 'close_game', 'make_short_url', 'export_data', 'sounds_list', 'brand_logo', 'brand_logo_remove', 'team_list', 'team_add', 'team_remove', 'team_update', 'team_reset_password', 'team_reset_2fa', 'account_info', 'change_password', 'new_backup_codes',
    'request_upgrade', 'master_billing', 'master_billing_save', 'master_test_mail', 'master_invoice_paid', 'master_invoice_void', 'master_invoice_resend', 'master_invoice_new', 'master_lead_update',
    'master_list', 'master_create', 'master_update', 'master_reset_password', 'master_reset_2fa', 'master_delete', 'master_new_code'];

$authActions = ['auth_status', 'create_master', 'login', 'verify_2fa', 'enroll_start', 'enroll_confirm', 'set_new_password', 'cancel_login'];

try {
    if (in_array($action, ['signup_info', 'signup', 'sales_request'], true)) {
        require __DIR__ . '/signup_api.php';
        exit;
    }

    if (in_array($action, $authActions, true)) {
        require __DIR__ . '/auth_api.php';
        exit;
    }

    if (in_array($action, $adminActions, true)) {
        $me = current_admin();
        if (!$me) fail('Please sign in again.', 'AUTH');
        $GLOBALS['RQ_USER'] = $me;
        $csrf = $_SERVER['HTTP_X_CSRF'] ?? in('csrf');
        if (!hash_equals($_SESSION['csrf'], (string) $csrf)) fail('Session expired — reload the page.', 'AUTH');
        if (strpos($action, 'master_') === 0 && !is_master($me)) fail('Only the master admin can do that.');
        if ($action !== 'logout') session_write_close(); // don't block other admin tabs
        require __DIR__ . '/admin_api.php';
        exit;
    }

    // Everything below is public (players + projector). The game code ("g") picks whose game it is.
    if ($action === 'find_game') {
        $a = admin_by_slug(in('g'));
        if (!$a || account_block_reason($a)) fail('We couldn\'t find a game with that code. Check the code on the big screen.');
        out(['g' => $a['handle']]);
    }
    $host = admin_by_slug(in('g'));
    if (!$host) fail('We couldn\'t find that game. Scan the QR code on the big screen again.', 'NO_GAME');
    if (account_block_reason($host)) fail('This game isn\'t available right now.', 'NO_GAME');
    set_owner($host['id']);

    switch ($action) {

    /* ---------------- player ---------------- */
    case 'state':
        tick();
        $ps = public_state();
        $c = ctx();
        $me = player_by_token((string) in('token'), $c['eventId']);
        $ps['me'] = null;
        $ps['emojis'] = EMOJIS;
        if ($me) {
            heartbeat($me);
            $cfg = get_config();
            $pid = (int) $me['id'];
            $board = leaderboard($c['eventId'], $cfg['leaderboardScope'] === 'session' ? $c['sessionId'] : null);
            $rank = null; $score = 0; $correct = 0;
            foreach ($board as $r) if ($r['id'] === $pid) { $rank = $r['rank']; $score = $r['score']; $correct = $r['correct']; }
            $eventScore = (int) qval('SELECT COALESCE(SUM(points),0) FROM responses WHERE player_id = ? AND scored = 1', [$pid]);
            $todayScore = (int) qval('SELECT COALESCE(SUM(points),0) FROM responses WHERE player_id = ? AND scored = 1 AND session_id = ?', [$pid, $c['sessionId']]);
            $ans = null;
            if ($ps['qIndex'] >= 0) {
                $ans = q1('SELECT choice, answer, correct, points, elapsed_ms FROM responses WHERE round_id = ? AND player_id = ?',
                    [$ps['roundId'], $pid]);
            }
            $mine = null;
            if ($ans) {
                $mine = ['choice' => $ans['choice'] === null ? null : (int) $ans['choice'],
                         'value' => $ans['answer'] === null ? null : json_decode($ans['answer'], true)];
                if ($ps['phase'] !== 'QUESTION') $mine += ['correct' => (bool) $ans['correct'], 'points' => (int) $ans['points'], 'ms' => (int) $ans['elapsed_ms']];
            }
            // idea board: my ideas, my votes, and (while voting) everyone else's ideas
            $ideas = null;
            $st = get_state();
            if ($ps['q'] && $ps['q']['type'] === 'ideas' && $st['roundId']) {
                $rows = idea_rows($st['roundId'], $st['q']);
                $votes = [];
                foreach (qall('SELECT v.idea_id, v.vote FROM idea_votes v JOIN ideas i ON i.id = v.idea_id WHERE i.round_id = ? AND v.player_id = ?', [$st['roundId'], $pid]) as $v)
                    $votes[(int) $v['idea_id']] = (int) $v['vote'];
                $ideas = ['mine' => array_values(array_map(function ($r) { return ['id' => $r['id'], 'text' => $r['text'], 'up' => $r['up'], 'removed' => $r['removed']]; },
                    array_filter($rows, function ($r) use ($pid) { return $r['player_id'] === $pid; })))];
                if (($st['ideaStage'] ?? '') === 'vote' || $ps['phase'] !== 'QUESTION') {
                    $ideas['board'] = array_values(array_map(function ($r) use ($votes, $pid) {
                        return ['id' => $r['id'], 'text' => $r['text'], 'up' => $r['up'], 'down' => $r['down'], 'removed' => $r['removed'],
                            'mine' => $r['player_id'] === $pid, 'myVote' => $votes[$r['id']] ?? 0];
                    }, $rows));
                }
            }
            $ps['me'] = [
                'id' => $pid, 'name' => $me['name'], 'emoji' => $me['emoji'], 'code' => $me['rejoin_code'],
                'score' => $score, 'eventScore' => $eventScore, 'todayScore' => $todayScore, 'correct' => $correct,
                'rank' => $ps['locked'] ? null : $rank, 'players' => count($board),
                'wins' => (int) qval('SELECT COUNT(*) FROM draws WHERE player_id = ?', [$pid]),
                'answer' => $mine, 'ideas' => $ideas,
            ];
        }
        out($ps);

    case 'join':
        if (!get_state()['launched']) fail('The host hasn\'t opened the game yet — hang tight!');
        $r = join_player(in('name'), in('emoji'));
        out($r);

    case 'rejoin':
        out(rejoin_player(in('name'), in('code')));

    case 'answer':
        $c = ctx();
        $me = player_by_token((string) in('token'), $c['eventId']);
        if (!$me) fail('Please join again.', 'NO_PLAYER');
        $raw = in('value', null);
        $val = $raw === null ? null : json_decode((string) $raw, true);
        submit_answer($me, (int) in('sessionId'), (int) in('qIndex'), in('choice', null), $val, (int) in('roundId', 0));
        tick();
        out([]);

    case 'idea_submit':
        $c = ctx();
        $me = player_by_token((string) in('token'), $c['eventId']);
        if (!$me) fail('Please join again.', 'NO_PLAYER');
        idea_submit($me, (int) in('roundId'), in('text'));
        out([]);

    case 'idea_vote':
        $c = ctx();
        $me = player_by_token((string) in('token'), $c['eventId']);
        if (!$me) fail('Please join again.', 'NO_PLAYER');
        idea_vote($me, (int) in('roundId'), (int) in('ideaId'), (int) in('vote'));
        out([]);

    case 'wave':
        $c = ctx();
        $me = player_by_token((string) in('token'), $c['eventId']);
        if (!$me) fail('Please join again.', 'NO_PLAYER');
        $last = (int) qval('SELECT MAX(created_at) FROM waves WHERE event_id = ? AND name = ?', [$c['eventId'], $me['name']]);
        if ($last > now_ms() - 5000) fail('Easy there! Wait a few seconds before waving again.');
        qexec('INSERT INTO waves (event_id, name, emoji, created_at) VALUES (?,?,?,?)', [$c['eventId'], $me['name'], $me['emoji'], now_ms()]);
        out([]);

    case 'set_emoji':
        $c = ctx();
        $me = player_by_token((string) in('token'), $c['eventId']);
        if (!$me) fail('Please join again.', 'NO_PLAYER');
        qexec('UPDATE players SET emoji = ? WHERE id = ?', [clean_emoji(in('emoji')), $me['id']]);
        out([]);

    /* ---------------- stage / projector ---------------- */
    case 'stage':
        tick();
        $ps = public_state();
        $c = ctx(); $cfg = get_config();
        $ps['counts'] = [
            'registered' => (int) qval('SELECT COUNT(*) FROM players WHERE event_id = ?', [$c['eventId']]),
            'active' => active_count($c['eventId'], $cfg),
            'answered' => $ps['roundId'] ? answered_count($ps['roundId']) : 0,
        ];
        $ps['live'] = round_live(get_state());
        $ps['stats'] = ($ps['live'] && isset($ps['live']['counts']) && $ps['phase'] !== 'QUESTION') ? $ps['live']['counts'] : null;
        $scopeSession = $cfg['leaderboardScope'] === 'session' ? $c['sessionId'] : null;
        $ps['leaderboard'] = $ps['locked'] ? [] : leaderboard($c['eventId'], $scopeSession, $ps['phase'] === 'PODIUM' ? 10 : 10);
        $ps['roundDraws'] = $ps['roundId'] ? array_map('draw_public',
            qall('SELECT * FROM draws WHERE round_id = ? ORDER BY id', [$ps['roundId']])) : [];
        $since = (int) in('since', 0);
        $ps['waves'] = qall('SELECT id, name, emoji FROM waves WHERE event_id = ? AND id > ? AND created_at > ? ORDER BY id LIMIT 10',
            [$c['eventId'], $since, now_ms() - 10000]);
        $ps['faces'] = qall('SELECT name, emoji FROM players WHERE event_id = ? AND last_seen >= ? ORDER BY created_at DESC LIMIT 60',
            [$c['eventId'], now_ms() - $cfg['activeWindowSec'] * 1000]);
        $ps['joinUrl'] = join_url($cfg);
        $ps['joinIsLocal'] = is_local_url($ps['joinUrl']);
        out($ps);

    default:
        fail('Unknown action.');
    }
} catch (UserError $e) {
    fail($e->getMessage(), $e->getMessage() === 'NAME_TAKEN' ? 'NAME_TAKEN' : null);
} catch (Throwable $e) {
    http_response_code(500);
    fail('Server error: ' . $e->getMessage());
}
