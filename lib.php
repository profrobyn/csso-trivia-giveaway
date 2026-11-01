<?php
/*
 * RizQuiz core library: database, settings, game state machine, scoring, prizes.
 * The server is the single source of truth; the admin, stage and player pages poll api.php.
 */
require_once __DIR__ . '/config.php';

const DRAW_ANIM_MS   = 3500;  // length of the prize "reel" animation
const GET_READY_MS   = 3000;  // countdown before answers open (lets every phone catch up)
const PRIZE_MODES    = ['random_correct', 'random_player', 'fastest'];

/* Account plans. players = max players per quiz event, quizzes = launches per calendar month (0 = unlimited). */
const PLANS = [
    'free'       => ['label' => 'Free',       'players' => 10,   'quizzes' => 2,  'branding' => false, 'ppt' => false, 'export' => false, 'team' => false],
    'basic'      => ['label' => 'Basic',      'players' => 50,   'quizzes' => 3,  'branding' => false, 'ppt' => false, 'export' => false, 'team' => false],
    'pro'        => ['label' => 'Pro',        'players' => 1000, 'quizzes' => 10, 'branding' => true,  'ppt' => true,  'export' => true,  'team' => false],
    'enterprise' => ['label' => 'Enterprise', 'players' => 0,    'quizzes' => 0,  'branding' => true,  'ppt' => true,  'export' => true,  'team' => true],
];
const EMOJIS = ['😀','😎','🤓','🥳','🤠','👻','👽','🤖','🦄','🐶','🐱','🦊','🐼','🐨','🐯','🦁','🐸','🐵','🐧','🐙',
                '🦋','🐝','🐢','🦖','🐳','🦩','🌵','🌻','🍕','🌮','🍩','🍓','🥑','🍿','☕','⚽','🏀','🎸','🎨','🎮',
                '🚀','⚡','🔥','🌈','⭐','🍀','💎','🎯','👑','🧠'];

class UserError extends Exception {}
require_once __DIR__ . '/types.php';
require_once __DIR__ . '/billing_lib.php';

function now_ms() { return (int) round(microtime(true) * 1000); }

/* ---------------------------------------------------------------- database */

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!extension_loaded('pdo_sqlite')) {
        throw new UserError('PHP\'s SQLite extension is turned off. In MAMP open File > Edit Template > PHP > php.ini, '
            . 'remove the ";" in front of "extension=pdo_sqlite" (or "extension=php_pdo_sqlite.dll"), save and restart the servers.');
    }
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_writable($dir)) throw new UserError('The folder ' . $dir . ' is not writable by PHP.');
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    }
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 15);
    $pdo->exec('PRAGMA busy_timeout = 15000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    migrate($pdo);
    return $pdo;
}

function migrate($pdo) {
    $v = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v < 1) migrate_v1($pdo);
    if ($v < 2) migrate_v2($pdo);
    if ($v < 3) {
        // v3: remember the question's name on each prize, for the winners log
        $pdo->exec('ALTER TABLE draws ADD COLUMN q_name TEXT');
        $pdo->exec('PRAGMA user_version = 3');
    }
    if ($v < 4) migrate_v4($pdo);
    if ($v < 5) {
        // v5: every admin gets a short web name, used for their own folder: /rizquiz/<name>/
        $pdo->exec('ALTER TABLE admins ADD COLUMN handle TEXT');
        foreach ($pdo->query('SELECT id, username FROM admins')->fetchAll() as $a) {
            $h = unique_handle($a['username'], (int) $a['id'], $pdo);
            $pdo->prepare('UPDATE admins SET handle = ? WHERE id = ?')->execute([$h, $a['id']]);
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_admins_handle ON admins(handle)');
        $pdo->exec('PRAGMA user_version = 5');
    }
    if ($v >= 6 && $v < 7) migrate_v7($pdo);
    if ($v < 6) {
        // v6: question types (typed answers), a record of every round played, and the idea board
        $pdo->exec('ALTER TABLE responses ADD COLUMN answer TEXT');
        $pdo->exec("CREATE TABLE IF NOT EXISTS rounds (
            id INTEGER PRIMARY KEY, event_id INTEGER, session_id INTEGER, bank_id INTEGER, q_index INTEGER, q TEXT, started_at INTEGER)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rounds_event ON rounds(event_id)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS ideas (
            id INTEGER PRIMARY KEY AUTOINCREMENT, round_id INTEGER NOT NULL, event_id INTEGER,
            player_id INTEGER REFERENCES players(id) ON DELETE CASCADE, text TEXT NOT NULL, created_at INTEGER)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ideas_round ON ideas(round_id)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_votes (
            idea_id INTEGER NOT NULL REFERENCES ideas(id) ON DELETE CASCADE,
            player_id INTEGER NOT NULL REFERENCES players(id) ON DELETE CASCADE,
            vote INTEGER NOT NULL, created_at INTEGER, PRIMARY KEY (idea_id, player_id))");
        $pdo->exec('PRAGMA user_version = 6');
        migrate_v7($pdo);
    }
    if ($v < 8) migrate_v8($pdo);
}

/** v7: plans (free/basic/pro/enterprise), enterprise team members, and a log of launches for monthly limits. */
function migrate_v7($pdo) {
    $pdo->exec("ALTER TABLE admins ADD COLUMN plan TEXT NOT NULL DEFAULT 'basic'");
    $pdo->exec("ALTER TABLE admins ADD COLUMN team_of INTEGER");
    $pdo->exec("UPDATE admins SET plan = 'pro' WHERE role <> 'master'");        // existing hosts keep everything they had
    $pdo->exec("UPDATE admins SET plan = 'enterprise' WHERE role = 'master'");
    $pdo->exec("CREATE TABLE IF NOT EXISTS launches (id INTEGER PRIMARY KEY AUTOINCREMENT, admin_id INTEGER, session_id INTEGER, created_at INTEGER)");
    $pdo->exec('PRAGMA user_version = 7');
}

/** v4: admin accounts. Everything that existed before belongs to account #1 (the master admin). */
function migrate_v4($pdo) {
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            display_name TEXT, org TEXT, phone TEXT,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'admin',
            slug TEXT NOT NULL UNIQUE,
            totp_secret TEXT, totp_enabled INTEGER DEFAULT 0, totp_last_step INTEGER DEFAULT 0, backup_codes TEXT,
            must_change_pw INTEGER DEFAULT 0,
            status TEXT DEFAULT 'active', expires_at INTEGER, notes TEXT,
            failed_logins INTEGER DEFAULT 0, locked_until INTEGER DEFAULT 0,
            created_at INTEGER, last_login INTEGER)");
        $pdo->exec("ALTER TABLE banks ADD COLUMN admin_id INTEGER NOT NULL DEFAULT 1");
        $pdo->exec("ALTER TABLE events ADD COLUMN admin_id INTEGER NOT NULL DEFAULT 1");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_banks_admin ON banks(admin_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_events_admin ON events(admin_id)");
        $pdo->exec("UPDATE settings SET k = 'a1:' || k WHERE k IN ('config','state','active_bank_id','current_event_id','current_session_id')");
        $pdo->exec('PRAGMA user_version = 4');
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

/* ------------------------------------------------------------------ owner */
/* Every admin account has its own questions, events, players, settings and game state.
   The "owner" is the admin whose game the current request is about. */

function set_owner($id) { $GLOBALS['RQ_OWNER'] = (int) $id; }
function owner() {
    $o = (int) ($GLOBALS['RQ_OWNER'] ?? 0);
    if (!$o) throw new UserError('No game selected.');
    return $o;
}

function migrate_v1($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
        label TEXT, started_at INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS players (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
        name TEXT NOT NULL, name_key TEXT NOT NULL, emoji TEXT,
        token TEXT NOT NULL UNIQUE, rejoin_code TEXT NOT NULL,
        created_at INTEGER, last_seen INTEGER,
        UNIQUE(event_id, name_key))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS responses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL,
        session_id INTEGER NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
        q_index INTEGER NOT NULL,
        player_id INTEGER NOT NULL REFERENCES players(id) ON DELETE CASCADE,
        choice INTEGER, correct INTEGER, elapsed_ms INTEGER, points INTEGER,
        scored INTEGER DEFAULT 0, created_at INTEGER,
        UNIQUE(session_id, q_index, player_id))");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_resp_player ON responses(event_id, player_id)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS draws (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL, session_id INTEGER, q_index INTEGER,
        player_id INTEGER REFERENCES players(id) ON DELETE SET NULL,
        winner_name TEXT, winner_emoji TEXT, mode TEXT, auto INTEGER DEFAULT 0,
        elapsed_ms INTEGER, candidates TEXT, note TEXT, created_at INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS waves (
        id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER, name TEXT, emoji TEXT, created_at INTEGER)");
    $pdo->exec('PRAGMA user_version = 1');
}

/** v2: multiple question banks + per-round ids (so different banks never collide on the same day). */
function migrate_v2($pdo) {
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS banks (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, questions TEXT NOT NULL DEFAULT '[]',
            created_at INTEGER, updated_at INTEGER)");
        $pdo->exec("CREATE TABLE responses_v2 (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL,
            session_id INTEGER NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
            round_id INTEGER NOT NULL, bank_id INTEGER DEFAULT 0, q_index INTEGER NOT NULL,
            player_id INTEGER NOT NULL REFERENCES players(id) ON DELETE CASCADE,
            choice INTEGER, correct INTEGER, elapsed_ms INTEGER, points INTEGER,
            scored INTEGER DEFAULT 0, created_at INTEGER,
            UNIQUE(round_id, player_id))");
        $pdo->exec("INSERT INTO responses_v2 (id, event_id, session_id, round_id, bank_id, q_index, player_id, choice, correct, elapsed_ms, points, scored, created_at)
                    SELECT id, event_id, session_id, session_id * 100000 + q_index, 0, q_index, player_id, choice, correct, elapsed_ms, points, scored, created_at FROM responses");
        $pdo->exec("DROP TABLE responses");
        $pdo->exec("ALTER TABLE responses_v2 RENAME TO responses");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_resp_player ON responses(event_id, player_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_resp_round ON responses(round_id)");
        $pdo->exec("ALTER TABLE draws ADD COLUMN round_id INTEGER");
        $pdo->exec("ALTER TABLE draws ADD COLUMN bank_id INTEGER DEFAULT 0");
        $pdo->exec("UPDATE draws SET round_id = session_id * 100000 + q_index WHERE q_index >= 0");
        // move the old single question list into a bank
        $old = $pdo->query("SELECT v FROM settings WHERE k = 'questions'")->fetchColumn();
        $old = $old ? json_decode($old, true) : [];
        $st = $pdo->prepare('INSERT INTO banks (name, questions, created_at, updated_at) VALUES (?,?,?,?)');
        $st->execute([$old ? 'My Questions' : 'Question Bank 1', json_encode($old ?: [], JSON_UNESCAPED_UNICODE), now_ms(), now_ms()]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT OR REPLACE INTO settings (k, v) VALUES (?, ?)')->execute(['active_bank_id', json_encode($id)]);
        $pdo->prepare('UPDATE responses SET bank_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE draws SET bank_id = ? WHERE q_index >= 0')->execute([$id]);
        $pdo->exec("DELETE FROM settings WHERE k = 'questions'");
        // the stored live state may point at old indexes; return to the lobby
        $pdo->exec("DELETE FROM settings WHERE k = 'state'");
        $pdo->exec('PRAGMA user_version = 2');
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

/** Run $fn inside an IMMEDIATE transaction (one writer at a time). */
function tx(callable $fn) {
    $pdo = db();
    if ($pdo->inTransaction()) return $fn($pdo);
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $r = $fn($pdo);
        $pdo->exec('COMMIT');
        return $r;
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

function q1($sql, $params = []) {
    $st = db()->prepare($sql); $st->execute($params);
    $r = $st->fetch(); return $r === false ? null : $r;
}
function qall($sql, $params = []) {
    $st = db()->prepare($sql); $st->execute($params); return $st->fetchAll();
}
function qval($sql, $params = []) {
    $st = db()->prepare($sql); $st->execute($params); $v = $st->fetchColumn(); return $v === false ? null : $v;
}
function qexec($sql, $params = []) {
    $st = db()->prepare($sql); $st->execute($params); return $st->rowCount();
}

/* ---------------------------------------------------------------- settings */

function setting_get($k, $default = null) {
    $v = qval('SELECT v FROM settings WHERE k = ?', ['a' . owner() . ':' . $k]);
    if ($v === null) return $default;
    $d = json_decode($v, true);
    return $d === null ? $default : $d;
}
function setting_set($k, $val) {
    qexec('INSERT OR REPLACE INTO settings (k, v) VALUES (?, ?)', ['a' . owner() . ':' . $k, json_encode($val, JSON_UNESCAPED_UNICODE)]);
}

function default_config() {
    return [
        'title'                  => 'RizQuiz',
        'prizeEveryQuestion'     => true,      // automatically draw a prize after every question
        'prizeMode'              => 'random_correct', // random_correct | random_player | fastest | mix
        'mixStyle'               => 'rotate',  // rotate | random
        'repeatWinners'          => 'event',   // allow | session | event
        'autoDrawDelay'          => 5,         // seconds after the answer reveal
        'announcePrize'          => true,      // show the prize type during the question
        'lockFinalN'             => 2,         // hide rankings for the final N questions (0 = never)
        'leaderboardScope'       => 'event',   // event (cumulative, multi-day) | session (today only)
        'autoEndWhenAllAnswered' => true,
        'activeWindowSec'        => 20,        // a player counts as "here" if their phone checked in this recently
        'showWifiWarning'        => false,
        'wifiText'               => 'Connect to the event Wi-Fi first, then scan the game QR code.',
        'brandLogo'              => '',        // Pro: uploaded logo (branding/…)
        'brandPrimary'           => '',        // Pro: main color (#hex), blank = RizQuiz pink
        'brandAccent'            => '',        // Pro: second color
        'brandBg'                => '',        // Pro: background color
        'questionSound'          => '',        // file name of the question music (blank = first found)
        'podiumSound'            => '',
        'wifiName'               => '',        // optional: show a "join our Wi-Fi" QR code in the lobby
        'wifiPass'               => '',
        'joinUrl'                => '',        // blank = detect automatically
        'shortUrl'               => '',        // optional short link (e.g. is.gd / bit.ly) that redirects to the join page
        'useShortUrl'            => false,     // put the short link in the QR code and on screen
        'defaultTime'            => 15,
        'showSignupLink'         => true,      // "Host your own quiz" link on the podium (Pro and up may hide it)
    ];
}
function get_config() { return array_merge(default_config(), setting_get('config', [])); }

function save_config($in) {
    $d = default_config(); $c = get_config();
    foreach ($d as $k => $def) {
        if (!array_key_exists($k, $in)) continue;
        $v = $in[$k];
        if (is_bool($def))      $c[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
        elseif (is_int($def))   $c[$k] = (int) $v;
        else                    $c[$k] = trim((string) $v);
    }
    if (!in_array($c['prizeMode'], array_merge(PRIZE_MODES, ['mix']), true)) $c['prizeMode'] = 'random_correct';
    if (!in_array($c['mixStyle'], ['rotate', 'random'], true)) $c['mixStyle'] = 'rotate';
    if (!in_array($c['repeatWinners'], ['allow', 'session', 'event'], true)) $c['repeatWinners'] = 'event';
    if (!in_array($c['leaderboardScope'], ['event', 'session'], true)) $c['leaderboardScope'] = 'event';
    $c['autoDrawDelay']   = max(1, min(60, $c['autoDrawDelay']));
    $c['lockFinalN']      = max(0, min(20, $c['lockFinalN']));
    $c['activeWindowSec'] = max(5, min(300, $c['activeWindowSec']));
    $c['defaultTime']     = max(5, min(300, $c['defaultTime']));
    if ($c['title'] === '') $c['title'] = 'RizQuiz';
    if ($c['shortUrl'] !== '' && !preg_match('#^https?://[^\s]+$#i', $c['shortUrl'])) throw new UserError('The short link must start with http:// or https://');
    if ($c['shortUrl'] === '') $c['useShortUrl'] = false;
    foreach (['brandPrimary', 'brandAccent', 'brandBg'] as $k) {
        if ($c[$k] !== '' && !preg_match('/^#[0-9a-f]{6}$/i', $c[$k])) $c[$k] = '';
    }
    $snd = scan_sounds();
    if ($c['questionSound'] !== '' && !in_array($c['questionSound'], $snd['question'], true)) $c['questionSound'] = '';
    if ($c['podiumSound'] !== '' && !in_array($c['podiumSound'], $snd['podium'], true)) $c['podiumSound'] = '';
    setting_set('config', $c);
    return $c;
}

/* ----------------------------------------------------------- question banks */

function active_bank($refresh = false) {
    static $cache = [];
    $o = owner();
    if ($refresh) unset($cache[$o]);
    if (isset($cache[$o])) return $cache[$o];
    $id = setting_get('active_bank_id');
    $b = $id ? q1('SELECT * FROM banks WHERE id = ? AND admin_id = ?', [$id, $o]) : null;
    if (!$b) $b = q1('SELECT * FROM banks WHERE admin_id = ? ORDER BY updated_at DESC LIMIT 1', [$o]);
    if (!$b) { $b = q1('SELECT * FROM banks WHERE id = ?', [bank_create('Question Bank 1', [])]); }
    if ((int) $id !== (int) $b['id']) setting_set('active_bank_id', (int) $b['id']);
    $b['id'] = (int) $b['id'];
    $b['qs'] = json_decode($b['questions'] ?: '[]', true) ?: [];
    return $cache[$o] = $b;
}

function get_questions() { return active_bank()['qs']; }

function bank_create($name, $qs) {
    $name = trim((string) $name) ?: ('Question Bank ' . ((int) qval('SELECT COUNT(*) FROM banks WHERE admin_id = ?', [owner()]) + 1));
    qexec('INSERT INTO banks (admin_id, name, questions, created_at, updated_at) VALUES (?,?,?,?,?)',
        [owner(), mb_substr_safe($name, 80), json_encode(array_values($qs), JSON_UNESCAPED_UNICODE), now_ms(), now_ms()]);
    return (int) db()->lastInsertId();
}
function bank_save($id, $qs) {
    qexec('UPDATE banks SET questions = ?, updated_at = ? WHERE id = ? AND admin_id = ?', [json_encode(array_values($qs), JSON_UNESCAPED_UNICODE), now_ms(), $id, owner()]);
}
function bank_get($id) {
    $b = q1('SELECT * FROM banks WHERE id = ? AND admin_id = ?', [(int) $id, owner()]);
    if (!$b) throw new UserError('That question bank no longer exists.');
    $b['qs'] = json_decode($b['questions'] ?: '[]', true) ?: [];
    return $b;
}
function banks_list() {
    $active = active_bank()['id'];
    $rows = qall('SELECT id, name, questions, created_at, updated_at FROM banks WHERE admin_id = ? ORDER BY updated_at DESC', [owner()]);
    return array_map(function ($b) use ($active) {
        $qs = json_decode($b['questions'] ?: '[]', true) ?: [];
        return ['id' => (int) $b['id'], 'name' => $b['name'], 'count' => count($qs),
            'preview' => array_slice(array_map(function ($q) { return $q['text']; }, $qs), 0, 3),
            'created' => (int) $b['created_at'], 'updated' => (int) $b['updated_at'], 'active' => (int) $b['id'] === $active];
    }, $rows);
}
function mb_substr_safe($s, $n) { return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n); }

function normalize_questions($list) {
    if (!is_array($list)) throw new UserError('Questions must be a list.');
    if (isset($list['questions']) && is_array($list['questions'])) $list = $list['questions'];
    $out = [];
    foreach (array_values($list) as $i => $q) { $n = normalize_question($q, $i); if ($n) $out[] = $n; }
    return $out;
}

/* ------------------------------------------------------- events & sessions */

function ctx($refresh = false) {
    static $cache = [];
    $o = owner();
    if ($refresh) unset($cache[$o]);
    if (isset($cache[$o])) return $cache[$o];
    $ev = setting_get('current_event_id');
    $event = $ev ? q1('SELECT * FROM events WHERE id = ? AND admin_id = ?', [$ev, $o]) : null;
    if (!$event) $event = q1('SELECT * FROM events WHERE admin_id = ? ORDER BY id DESC LIMIT 1', [$o]);
    if ($event && (int) $ev !== (int) $event['id']) { setting_set('current_event_id', (int) $event['id']); setting_set('current_session_id', 0); }
    if (!$event) {
        $event = create_event('My Quiz Event');
    }
    $sid = setting_get('current_session_id');
    $session = $sid ? q1('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [$sid, $event['id']]) : null;
    if (!$session) {
        $session = q1('SELECT * FROM sessions WHERE event_id = ? ORDER BY id DESC LIMIT 1', [$event['id']]);
        if (!$session) $session = create_session($event['id'], 'Day 1');
        setting_set('current_session_id', (int) $session['id']);
    }
    $ctx = ['event' => $event, 'session' => $session, 'eventId' => (int) $event['id'], 'sessionId' => (int) $session['id']];
    return $cache[$o] = $ctx;
}

function create_event($name) {
    qexec('INSERT INTO events (admin_id, name, created_at) VALUES (?, ?, ?)', [owner(), $name, now_ms()]);
    $id = (int) db()->lastInsertId();
    setting_set('current_event_id', $id);
    $s = create_session($id, 'Day 1');
    setting_set('current_session_id', (int) $s['id']);
    set_state(default_state());
    return q1('SELECT * FROM events WHERE id = ?', [$id]);
}
function create_session($eventId, $label) {
    qexec('INSERT INTO sessions (event_id, label, started_at) VALUES (?, ?, ?)', [$eventId, $label, now_ms()]);
    return q1('SELECT * FROM sessions WHERE id = ?', [(int) db()->lastInsertId()]);
}

/* ------------------------------------------------------------- game state */

function default_state() {
    return ['launched' => false, 'phase' => 'LOBBY', 'qIndex' => -1, 'q' => null, 'totalQ' => 0, 'startedAt' => 0, 'timeLimit' => 0,
            'revealedAt' => 0, 'prizeMode' => null, 'drawId' => null, 'drawAt' => 0, 'autoDrawn' => false,
            'roundId' => 0, 'bankId' => 0, 'adhoc' => false, 'mainIndex' => -1, 'ideaStage' => null,
            'version' => now_ms()];
}
/** Back to the lobby (fresh round) without closing a launched game. */
function reset_to_lobby($totalQ = null) {
    $s = get_state();
    set_state(array_merge(default_state(), ['launched' => (bool) $s['launched'], 'launchedAt' => $s['launchedAt'] ?? 0,
        'totalQ' => $totalQ === null ? count(get_questions()) : $totalQ]));
}
function get_state() { return array_merge(default_state(), setting_get('state', [])); }
function set_state($s) { $s['version'] = now_ms(); setting_set('state', $s); }

function resolve_prize_mode($q, $idx, $cfg) {
    $m = $q['prizeMode'] ?? 'default';
    if ($m === 'none' || in_array($m, PRIZE_MODES, true)) return $m;
    $m = $cfg['prizeMode'];
    if ($m === 'mix') {
        return $cfg['mixStyle'] === 'random' ? PRIZE_MODES[random_int(0, 2)] : PRIZE_MODES[$idx % 3];
    }
    return $m;
}

function active_count($eventId, $cfg) {
    return (int) qval('SELECT COUNT(*) FROM players WHERE event_id = ? AND last_seen >= ?',
        [$eventId, now_ms() - $cfg['activeWindowSec'] * 1000]);
}
function answered_count($roundId) {
    return (int) qval('SELECT COUNT(*) FROM responses WHERE round_id = ?', [(int) $roundId]);
}

/** Lazy clock: called on every poll so the game advances even without a host click. */
function tick() {
    $s = get_state();
    $now = now_ms();
    $cfg = get_config();
    $c = ctx();
    if ($s['phase'] === 'QUESTION') {
        $expired = $now >= $s['startedAt'] + $s['timeLimit'] * 1000 + 400;
        $all = false;
        if (!$expired && $cfg['autoEndWhenAllAnswered'] && $now >= $s['startedAt'] && q_type($s['q']) !== 'ideas') {
            $active = active_count($c['eventId'], $cfg);
            $all = $active > 0 && answered_count($s['roundId']) >= $active;
        }
        if ($expired || $all) advance_question($s['roundId'], ($s['ideaStage'] ?? null));
    } elseif ($s['phase'] === 'REVEAL') {
        if ($cfg['prizeEveryQuestion'] && $s['prizeMode'] && $s['prizeMode'] !== 'none' && empty($s['autoDrawn'])
            && $now >= $s['revealedAt'] + $cfg['autoDrawDelay'] * 1000) {
            try { do_draw($s['prizeMode'], true); } catch (UserError $e) { /* nothing eligible */ }
        }
    }
}

/**
 * Start question $idx of the loaded bank, or — when $bankId names a different bank — run that one
 * question as a "bonus" (pulled up by name) without changing the loaded bank or its place in the order.
 */
function start_question($idx, $bankId = null) {
    $active = active_bank();
    $bank = ($bankId && (int) $bankId !== $active['id']) ? bank_get($bankId) : $active;
    $bank['id'] = (int) $bank['id'];
    $adhoc = $bank['id'] !== $active['id'];
    $qs = $bank['qs'];
    if (!isset($qs[$idx])) throw new UserError('That question does not exist.');
    $cfg = get_config();
    $c = ctx();
    $prev = get_state();
    return tx(function () use ($qs, $idx, $cfg, $c, $bank, $adhoc, $active, $prev) {
        // replaying the same question from the same bank on the same day replaces its earlier answers
        qexec('DELETE FROM responses WHERE session_id = ? AND bank_id = ? AND q_index = ?', [$c['sessionId'], $bank['id'], $idx]);
        qexec('DELETE FROM draws WHERE session_id = ? AND bank_id = ? AND q_index = ?', [$c['sessionId'], $bank['id'], $idx]);
        qexec('DELETE FROM ideas WHERE round_id IN (SELECT id FROM rounds WHERE session_id = ? AND bank_id = ? AND q_index = ?)', [$c['sessionId'], $bank['id'], $idx]);
        qexec('DELETE FROM rounds WHERE session_id = ? AND bank_id = ? AND q_index = ?', [$c['sessionId'], $bank['id'], $idx]);
        $q = normalize_question($qs[$idx], $idx) ?: $qs[$idx];
        $s = default_state();
        $s['launched'] = true;
        $s['launchedAt'] = $prev['launchedAt'] ?? now_ms();
        $s['phase'] = 'QUESTION';
        $s['qIndex'] = $idx;
        $s['adhoc'] = $adhoc;
        $s['mainIndex'] = $adhoc ? (int) $prev['mainIndex'] : $idx;
        $s['totalQ'] = count($active['qs']);
        $s['q'] = $q;
        $s['timeLimit'] = (int) $q['timeLimit'];
        $s['startedAt'] = now_ms() + GET_READY_MS;
        $s['prizeMode'] = resolve_prize_mode($q, $idx, $cfg);
        $s['roundId'] = now_ms();
        $s['bankId'] = $bank['id'];
        $s['ideaStage'] = q_type($q) === 'ideas' ? 'submit' : null;
        if (q_type($q) === 'ideas') { [$q['bestPtsCalc'], $q['voterPtsCalc']] = idea_points($q, $active['qs']); $s['q'] = $q; }
        qexec('INSERT OR REPLACE INTO rounds (id, event_id, session_id, bank_id, q_index, q, started_at) VALUES (?,?,?,?,?,?,?)',
            [$s['roundId'], $c['eventId'], $c['sessionId'], $bank['id'], $idx, json_encode($q, JSON_UNESCAPED_UNICODE), $s['startedAt']]);
        set_state($s);
        return $s;
    });
}

/**
 * Timer ran out / host clicked "End timer". Idea boards move from sharing to voting first;
 * everything else goes to the answer reveal. $roundId/$stage guard against double moves from parallel polls.
 */
function advance_question($roundId = null, $stage = null) {
    $moved = tx(function () use ($roundId, $stage) {
        $s = get_state();
        if ($s['phase'] !== 'QUESTION') return true;
        if ($roundId !== null && (int) $roundId !== (int) $s['roundId']) return true;
        if ($roundId !== null && ($s['ideaStage'] ?? null) !== $stage) return true;
        if (q_type($s['q']) === 'ideas' && ($s['ideaStage'] ?? 'submit') === 'submit'
            && (int) qval('SELECT COUNT(*) FROM ideas WHERE round_id = ?', [$s['roundId']]) > 1) {
            $s['ideaStage'] = 'vote';
            $s['startedAt'] = now_ms() + 1500;
            $s['timeLimit'] = (int) $s['q']['voteTime'];
            set_state($s);
            return true;
        }
        return false;
    });
    if (!$moved) reveal_current();
}

function reveal_current() {
    $c = ctx();
    tx(function () use ($c) {
        $s = get_state();
        if ($s['phase'] !== 'QUESTION') return;
        if (q_type($s['q']) === 'ideas') score_idea_round($s);
        qexec('UPDATE responses SET scored = 1 WHERE round_id = ?', [$s['roundId']]);
        $s['phase'] = 'REVEAL';
        $s['revealedAt'] = now_ms();
        set_state($s);
    });
}

function set_phase($phase) {
    tx(function () use ($phase) {
        $s = get_state();
        if ($s['phase'] === 'QUESTION') {
            // finish the question first so nobody loses points
            if (q_type($s['q']) === 'ideas') score_idea_round($s);
            qexec('UPDATE responses SET scored = 1 WHERE round_id = ?', [$s['roundId']]);
            $s['revealedAt'] = now_ms();
        }
        $s['phase'] = $phase;
        if ($phase === 'LOBBY') { $s = array_merge(default_state(), ['launched' => $s['launched'], 'launchedAt' => $s['launchedAt'] ?? 0, 'qIndex' => $s['qIndex'], 'mainIndex' => $s['mainIndex'], 'totalQ' => count(get_questions())]); }
        set_state($s);
    });
}

/* --------------------------------------------------------------- answers */

function submit_answer($player, $sessionId, $qIndex, $choice, $value = null, $roundId = null) {
    $c = ctx();
    $s = get_state();
    $now = now_ms();
    if ($s['phase'] !== 'QUESTION' || (int) $qIndex !== (int) $s['qIndex'] || (int) $sessionId !== $c['sessionId']
        || ($roundId && (int) $roundId !== (int) $s['roundId']))
        throw new UserError('This question is closed.');
    if ($now < $s['startedAt'] - 250) throw new UserError('Not open yet!');
    $limitMs = $s['timeLimit'] * 1000;
    $elapsed = max(0, $now - $s['startedAt']);
    if ($elapsed > $limitMs + 400) throw new UserError('Time is up!');
    $q = $s['q'];
    [$ch, $ans, $correct] = evaluate_answer($q, $choice, $value);
    // scored types: centiseconds remaining for a correct answer (as before); opinion types: participation points
    $points = q_scored($q) ? ($correct ? (int) floor(max(0, $limitMs - $elapsed) / 10) : 0) : (int) ($q['points'] ?? 0);
    qexec('INSERT OR IGNORE INTO responses (event_id, session_id, round_id, bank_id, q_index, player_id, choice, answer, correct, elapsed_ms, points, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [$c['eventId'], $c['sessionId'], $s['roundId'], $s['bankId'], $qIndex, $player['id'], $ch,
        $ans === null ? null : json_encode($ans, JSON_UNESCAPED_UNICODE), $correct, $elapsed, $points, $now]);
    return true;
}

/* ------------------------------------------------------------ leaderboard */

function leaderboard($eventId, $sessionId = null, $limit = 0) {
    $join = $sessionId ? ' AND r.session_id = ' . (int) $sessionId : '';
    $having = $sessionId ? ' HAVING COUNT(r.id) > 0' : '';
    $sql = "SELECT p.id, p.name, p.emoji,
              COALESCE(SUM(CASE WHEN r.scored = 1 THEN r.points ELSE 0 END), 0) AS score,
              COALESCE(SUM(CASE WHEN r.scored = 1 AND r.correct = 1 THEN 1 ELSE 0 END), 0) AS correct
            FROM players p LEFT JOIN responses r ON r.player_id = p.id $join
            WHERE p.event_id = ? GROUP BY p.id $having
            ORDER BY score DESC, correct DESC, p.id ASC" . ($limit ? ' LIMIT ' . (int) $limit : '');
    $rows = qall($sql, [$eventId]);
    foreach ($rows as $i => &$r) { $r['rank'] = $i + 1; $r['id'] = (int) $r['id']; $r['score'] = (int) $r['score']; $r['correct'] = (int) $r['correct']; }
    return $rows;
}

function rankings_locked($s, $cfg) {
    if (!in_array($s['phase'], ['QUESTION', 'REVEAL', 'WINNER', 'LEADERBOARD'], true)) return false;
    $n = (int) $cfg['lockFinalN'];
    $pos = !empty($s['adhoc']) ? (int) $s['mainIndex'] : (int) $s['qIndex'];
    return $n > 0 && $s['totalQ'] > 1 && $pos >= $s['totalQ'] - $n;
}

/* ----------------------------------------------------------------- prizes */

function excluded_winner_ids($rule, $eventId, $sessionId) {
    if ($rule === 'allow') return [];
    $sql = $rule === 'session'
        ? 'SELECT DISTINCT player_id FROM draws WHERE session_id = ? AND player_id IS NOT NULL'
        : 'SELECT DISTINCT player_id FROM draws WHERE event_id = ? AND player_id IS NOT NULL';
    return array_map('intval', array_column(qall($sql, [$rule === 'session' ? $sessionId : $eventId]), 'player_id'));
}

function do_draw($mode, $auto = false) {
    if (!in_array($mode, PRIZE_MODES, true)) throw new UserError('Unknown prize type.');
    $cfg = get_config();
    $c = ctx();
    return tx(function () use ($mode, $auto, $cfg, $c) {
        $s = get_state();
        if ($s['phase'] === 'QUESTION') throw new UserError('Wait until the question has ended.');
        if ($auto && ($s['phase'] !== 'REVEAL' || !empty($s['autoDrawn']))) return null;
        $q = (int) $s['qIndex'];
        $hasRound = $q >= 0 && in_array($s['phase'], ['REVEAL', 'WINNER', 'LEADERBOARD'], true);
        if ($mode !== 'random_player' && !$hasRound)
            throw new UserError('This prize type needs a question that was just played. Use "Random player" instead, or run a question first.');
        $excluded = excluded_winner_ids($cfg['repeatWinners'], $c['eventId'], $c['sessionId']);

        $isIdeas = $hasRound && q_type($s['q']) === 'ideas';
        if ($mode === 'fastest' && $isIdeas) {
            // idea board: "fastest" means the author of the best (most nominated) idea
            $top = top_idea($s);
            $pool = $top ? qall('SELECT id, name, emoji FROM players WHERE id = ?', [$top['player_id']]) : [];
        } elseif ($mode === 'random_player') {
            $pool = qall('SELECT id, name, emoji FROM players WHERE event_id = ? AND last_seen >= ? ORDER BY id',
                [$c['eventId'], now_ms() - $cfg['activeWindowSec'] * 1000]);
            if (!$pool && $hasRound) {
                $pool = qall('SELECT p.id, p.name, p.emoji FROM responses r JOIN players p ON p.id = r.player_id
                              WHERE r.round_id = ?', [$s['roundId']]);
            }
        } else {
            $pool = qall('SELECT p.id, p.name, p.emoji, r.elapsed_ms FROM responses r JOIN players p ON p.id = r.player_id
                          WHERE r.round_id = ? AND r.correct = 1 ORDER BY r.elapsed_ms ASC, r.id ASC',
                [$s['roundId']]);
        }
        $poolBefore = count($pool);
        $pool = array_values(array_filter($pool, function ($p) use ($excluded) { return !in_array((int) $p['id'], $excluded, true); }));
        $note = null;
        if (!$pool) $note = $poolBefore ? 'all_won' : ($mode === 'random_player' ? 'no_players' : ($isIdeas && $mode === 'fastest' ? 'no_ideas' : 'no_correct'));
        elseif ($isIdeas && $mode === 'fastest') $note = 'best_idea';

        $winner = null;
        if ($pool) $winner = $mode === 'fastest' ? $pool[0] : $pool[random_int(0, count($pool) - 1)];

        // names for the stage "reel"
        $reel = $pool; shuffle($reel); $reel = array_slice($reel, 0, 24);
        if ($winner && !in_array($winner['id'], array_column($reel, 'id'))) $reel[] = $winner;
        $cands = array_map(function ($p) { return ['name' => $p['name'], 'emoji' => $p['emoji'], 'ms' => isset($p['elapsed_ms']) ? (int) $p['elapsed_ms'] : null]; },
            $mode === 'fastest' ? array_slice($pool, 0, 5) : $reel);

        qexec('INSERT INTO draws (event_id, session_id, round_id, bank_id, q_index, player_id, winner_name, winner_emoji, mode, auto, elapsed_ms, candidates, note, created_at, q_name)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $c['eventId'], $c['sessionId'], $hasRound ? $s['roundId'] : null, $hasRound ? $s['bankId'] : 0, $hasRound ? $q : -1, $winner ? $winner['id'] : null,
            $winner ? $winner['name'] : null, $winner ? $winner['emoji'] : null, $mode, $auto ? 1 : 0,
            ($winner && isset($winner['elapsed_ms'])) ? (int) $winner['elapsed_ms'] : null,
            json_encode($cands, JSON_UNESCAPED_UNICODE), $note, now_ms(), ($hasRound && !empty($s['q']['name'])) ? $s['q']['name'] : null]);
        $id = (int) db()->lastInsertId();
        $s['phase'] = 'WINNER';
        $s['drawId'] = $id;
        $s['drawAt'] = now_ms();
        $s['autoDrawn'] = true;
        set_state($s);
        return $id;
    });
}

function draw_public($row) {
    if (!$row) return null;
    return [
        'id' => (int) $row['id'], 'mode' => $row['mode'], 'auto' => (int) $row['auto'], 'qIndex' => (int) $row['q_index'],
        'playerId' => $row['player_id'] === null ? null : (int) $row['player_id'],
        'name' => $row['winner_name'], 'emoji' => $row['winner_emoji'],
        'ms' => $row['elapsed_ms'] === null ? null : (int) $row['elapsed_ms'],
        'candidates' => json_decode($row['candidates'] ?: '[]', true), 'at' => (int) $row['created_at'],
        'note' => $row['note'] ?? null, 'qName' => $row['q_name'] ?? null,
    ];
}

/* ---------------------------------------------------------------- players */

function name_key($name) { return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name); }

function clean_name($name) {
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    $len = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($len < 1) throw new UserError('Please enter your name.');
    if ($len > 30) throw new UserError('Please use 30 characters or fewer.');
    return $name;
}
function clean_emoji($e) { return in_array($e, EMOJIS, true) ? $e : EMOJIS[0]; }

function new_code() {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; $s = '';
    for ($i = 0; $i < 5; $i++) $s .= $chars[random_int(0, strlen($chars) - 1)];
    return $s;
}

function player_by_token($token, $eventId) {
    if (!$token || !preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    return q1('SELECT * FROM players WHERE token = ? AND event_id = ?', [$token, $eventId]);
}

function join_player($name, $emoji) {
    $c = ctx();
    $name = clean_name($name);
    $key = name_key($name);
    if (name_blocked($name)) throw new UserError('That name isn\'t allowed in this game. Please choose a different name.');
    if (q1('SELECT id FROM players WHERE event_id = ? AND name_key = ?', [$c['eventId'], $key]))
        throw new UserError('NAME_TAKEN');
    $max = plan()['players'];
    if ($max && (int) qval('SELECT COUNT(*) FROM players WHERE event_id = ?', [$c['eventId']]) >= $max)
        throw new UserError('Sorry — this game is full (' . $max . ' players). Ask the host about joining.');
    $token = bin2hex(random_bytes(16));
    $code = new_code();
    try {
        qexec('INSERT INTO players (event_id, name, name_key, emoji, token, rejoin_code, created_at, last_seen) VALUES (?,?,?,?,?,?,?,?)',
            [$c['eventId'], $name, $key, clean_emoji($emoji), $token, $code, now_ms(), now_ms()]);
    } catch (PDOException $e) {
        throw new UserError('NAME_TAKEN');
    }
    return ['token' => $token, 'code' => $code];
}

/* Names the host blocked (e.g. something offensive). Matching ignores case, spaces and punctuation. */
function block_key($name) {
    $k = function_exists('mb_strtolower') ? mb_strtolower((string) $name, 'UTF-8') : strtolower((string) $name);
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $k);
}
function blocked_names() { $v = setting_get('blockedNames', []); return is_array($v) ? $v : []; }
function name_blocked($name) {
    $k = block_key($name);
    if ($k === '') return false;
    foreach (blocked_names() as $b) if (($b['key'] ?? '') === $k) return true;
    return false;
}

function rejoin_player($name, $code) {
    $c = ctx();
    $p = q1('SELECT * FROM players WHERE event_id = ? AND name_key = ?', [$c['eventId'], name_key(clean_name($name))]);
    if (!$p || strtoupper(trim($code)) !== $p['rejoin_code']) throw new UserError('That name and rejoin code don\'t match.');
    qexec('UPDATE players SET last_seen = ? WHERE id = ?', [now_ms(), $p['id']]);
    return ['token' => $p['token'], 'code' => $p['rejoin_code']];
}

function heartbeat($p) {
    if ((int) $p['last_seen'] < now_ms() - 4000) qexec('UPDATE players SET last_seen = ? WHERE id = ?', [now_ms(), $p['id']]);
}

/* ------------------------------------------------------------ public view */

/** Web address of the RizQuiz folder (where index.php, setup.php … live). */
function base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (defined('RQ_HOST_DIR')) $dir = dirname($dir);   // request came through a host folder (/rizquiz/<name>/)
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($dir, '/') . '/';
}
/** Full player link for the current admin: <RizQuiz folder>/<admin web name>/ */
function long_join_url($cfg) {
    $handle = qval('SELECT handle FROM admins WHERE id = ?', [owner()]);
    $base = !empty($cfg['joinUrl']) ? rtrim(preg_replace('/(index\.php)?(\?.*)?$/', '', $cfg['joinUrl']), '/') . '/' : base_url();
    return $base . rawurlencode($handle) . '/';
}
function http_get($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'RizQuiz']);
        $r = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($r === false) throw new UserError('Could not reach the link-shortening service (' . $err . ').');
        return $r;
    }
    $r = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'RizQuiz', 'ignore_errors' => true]]));
    if ($r === false) throw new UserError('Could not reach the link-shortening service. Is this computer online?');
    return $r;
}
/** The link the QR code uses: the short link when turned on, otherwise the full link. Unique to each admin. */
function join_url($cfg) {
    if (!empty($cfg['useShortUrl']) && !empty($cfg['shortUrl'])) return $cfg['shortUrl'];
    return long_join_url($cfg);
}
function is_local_url($url) {
    $h = parse_url($url, PHP_URL_HOST);
    return in_array($h, ['localhost', '127.0.0.1', '::1'], true);
}

/** "Make your own quiz" link shown at the end of the game, or null. Free and Basic always show it. */
function signup_link($cfg) {
    if (!billing_get()['signupsOpen']) return null;
    if (empty($cfg['showSignupLink']) && plan()['branding']) return null;
    $base = !empty($cfg['joinUrl']) ? rtrim(preg_replace('/(index\.php)?(\?.*)?$/', '', $cfg['joinUrl']), '/') . '/' : base_url();
    return $base . 'signup.php';
}

/** State that is safe to show everyone (no correct answer while the question is open). */
function public_state() {
    $s = get_state(); $cfg = get_config(); $c = ctx();
    $q = $s['q'] ? public_question($s['q'], $s) : null;
    $draw = null;
    if ($s['phase'] === 'WINNER' && $s['drawId']) $draw = draw_public(q1('SELECT * FROM draws WHERE id = ?', [$s['drawId']]));
    return [
        'now' => now_ms(), 'title' => $cfg['title'],
        'event' => ['id' => $c['eventId'], 'name' => $c['event']['name']],
        'session' => ['id' => $c['sessionId'], 'label' => $c['session']['label']],
        'phase' => $s['phase'], 'qIndex' => (int) $s['qIndex'], 'totalQ' => (int) $s['totalQ'] ?: count(get_questions()),
        'q' => $q, 'startedAt' => (int) $s['startedAt'], 'timeLimit' => (int) $s['timeLimit'],
        'revealedAt' => (int) $s['revealedAt'], 'prizeMode' => $s['prizeMode'],
        'announcePrize' => (bool) $cfg['announcePrize'], 'prizeEveryQuestion' => (bool) $cfg['prizeEveryQuestion'],
        'autoDrawDelay' => (int) $cfg['autoDrawDelay'],
        'drawAt' => (int) $s['drawAt'], 'drawAnimMs' => DRAW_ANIM_MS, 'draw' => $draw,
        'locked' => rankings_locked($s, $cfg), 'scope' => $cfg['leaderboardScope'],
        'showWifiWarning' => (bool) $cfg['showWifiWarning'], 'wifiText' => $cfg['wifiText'],
        'wifiName' => $cfg['wifiName'], 'wifiPass' => $cfg['wifiPass'],
        'version' => $s['version'], 'roundId' => (int) $s['roundId'],
        'adhoc' => !empty($s['adhoc']), 'mainIndex' => (int) $s['mainIndex'],
        'launched' => !empty($s['launched']),
        'sounds' => chosen_sounds($cfg),
        'brand' => brand_public($cfg), 'signupUrl' => signup_link($cfg),
        'gameCode' => strtoupper((string) qval('SELECT slug FROM admins WHERE id = ?', [owner()])),
        'qName' => ($s['q'] && !empty($s['q']['name'])) ? $s['q']['name'] : null,
        'bank' => ['id' => active_bank()['id'], 'name' => active_bank()['name']],
    ];
}

/* -------------------------------------------------------------- accounts */

function admin_session() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('rizquiz_admin');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function admin_by_id($id) { return q1('SELECT * FROM admins WHERE id = ?', [(int) $id]); }
/** Find a host by game code or web name (both work in links and in the code box). */
function admin_by_slug($slug) {
    $slug = strtolower(trim((string) $slug));
    if ($slug === '') return null;
    $a = q1('SELECT * FROM admins WHERE handle = ?', [preg_replace('/[^a-z0-9-]/', '', $slug)]);
    return $a ?: q1('SELECT * FROM admins WHERE slug = ?', [preg_replace('/[^a-z0-9]/', '', $slug)]);
}

/* -------------------------------------------- per-admin web folders */
const RESERVED_HANDLES = ['assets', 'partials', 'data', 'api', 'admin', 'login', 'setup', 'live', 'master', 'stage', 'index', 'lib', 'config', 'auth', 'signup', 'invoice', 'billing', 'branding', 'sounds', 'tools', 'types', 'vendor', 'www', 'help', 'support', 'pricing'];

function clean_handle($h) {
    $h = strtolower(trim((string) $h));
    $h = preg_replace('/@.*$/', '', $h);              // dean@college.edu -> dean
    $h = preg_replace('/[^a-z0-9]+/', '-', $h);
    return trim(substr($h, 0, 30), '-');
}
function unique_handle($wanted, $exceptId = 0, $pdo = null) {
    $pdo = $pdo ?: db();
    $base = clean_handle($wanted) ?: 'host';
    if (in_array($base, RESERVED_HANDLES, true) || strlen($base) < 2) $base .= '-quiz';
    $h = $base; $n = 2;
    $st = $pdo->prepare('SELECT id FROM admins WHERE handle = ? AND id <> ?');
    while (true) { $st->execute([$h, $exceptId]); if (!$st->fetch()) return $h; $h = $base . $n++; }
}
/**
 * Each admin gets a small folder (e.g. /rizquiz/dean/) holding a tiny index.php that opens
 * the main player page for that admin's game. The QR code points there.
 */
function ensure_host_folder($handle) {
    $dir = __DIR__ . '/' . $handle;
    if (!is_dir($dir) && !@mkdir($dir, 0775)) return false;
    $php = "<?php\n// RizQuiz player page for host \"" . $handle . "\". Created automatically - do not edit.\n"
         . "\$_GET['g'] = " . var_export($handle, true) . ";\ndefine('RQ_HOST_DIR', true);\nrequire __DIR__ . '/../index.php';\n";
    $f = $dir . '/index.php';
    if (!file_exists($f) || file_get_contents($f) !== $php) return @file_put_contents($f, $php) !== false;
    return true;
}
function remove_host_folder($handle) {
    $dir = __DIR__ . '/' . $handle;
    if ($handle && is_file($dir . '/index.php') && strpos(file_get_contents($dir . '/index.php'), 'RizQuiz player page') !== false) {
        @unlink($dir . '/index.php'); @rmdir($dir);
    }
}
function admins_count() { return (int) qval('SELECT COUNT(*) FROM admins'); }
function is_master($a) { return $a && $a['role'] === 'master'; }

/** Why an account can't be used right now, or null if it's fine. */
function account_block_reason($a) {
    if (!$a) return 'Account not found.';
    if (is_master($a)) return null;
    if (!empty($a['team_of'])) {                         // team members follow their organization's account
        $own = admin_by_id($a['team_of']);
        if (!$own) return 'Your organization\'s account no longer exists.';
        if ($a['status'] !== 'active') return 'Your team access is paused. Please contact your account owner.';
        return account_block_reason($own);
    }
    if ($a['status'] !== 'active') return 'This account is paused. Please contact the RizQuiz administrator.';
    if ($a['expires_at'] && (int) $a['expires_at'] < now_ms()) return 'This account\'s license has expired. Please contact the RizQuiz administrator.';
    return null;
}

/** The fully signed-in admin (password + authenticator), or null. */
function current_admin() {
    admin_session();
    if (empty($_SESSION['rq_admin_id'])) return null;
    $a = admin_by_id($_SESSION['rq_admin_id']);
    if (!$a || account_block_reason($a)) { unset($_SESSION['rq_admin_id']); return null; }
    set_owner($a['id']);
    return $a;
}
function owner_account() { return admin_by_id(owner()); }
/**
 * Enterprise organizations: one "organization master" account (team_of = NULL, plan = enterprise) and
 * any number of member accounts (team_of = master's id). Members run their own quizzes with their own
 * QR codes, but use the organization's plan and its official branding.
 */
function org_root($a) { return ($a && !empty($a['team_of'])) ? (admin_by_id($a['team_of']) ?: $a) : $a; }
function is_org_member($a = null) { $a = $a ?: owner_account(); return $a && !empty($a['team_of']); }

/* ------------------------------------------------------------------ plans */
function plan_key($ownerRow = null) {
    $o = org_root($ownerRow ?: owner_account());
    if (!$o) return 'free';
    if (is_master($o)) return 'enterprise';
    if (plan_expired($o)) return 'free';                 // a paid year ran out
    return isset(PLANS[$o['plan'] ?? '']) ? $o['plan'] : 'basic';
}
function plan($ownerRow = null) { $k = plan_key($ownerRow); return PLANS[$k] + ['key' => $k]; }
function require_feature($feature, $what) {
    if (!plan()[$feature]) throw new UserError($what . ' is part of the Pro plan. Ask your RizQuiz administrator to upgrade your account.');
}
function month_start_ms() { return strtotime(date('Y-m-01 00:00:00')) * 1000; }
function quizzes_this_month() {
    return (int) qval('SELECT COUNT(DISTINCT session_id) FROM launches WHERE admin_id = ? AND created_at >= ?', [owner(), month_start_ms()]);
}
function plan_usage() {
    $p = plan();
    return $p + ['quizzesUsed' => quizzes_this_month(), 'playersInEvent' => (int) qval('SELECT COUNT(*) FROM players WHERE event_id = ?', [ctx()['eventId']])];
}

function new_slug() {
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    do {
        $s = '';
        for ($i = 0; $i < 6; $i++) $s .= $chars[random_int(0, strlen($chars) - 1)];
    } while (q1('SELECT id FROM admins WHERE slug = ?', [$s]));
    return $s;
}

function validate_password($pw) {
    if (strlen($pw) < 10) throw new UserError('Passwords need at least 10 characters.');
    if (!preg_match('/[a-z]/i', $pw) || !preg_match('/\d/', $pw)) throw new UserError('Use a mix of letters and numbers in the password.');
}

function clean_username($u) {
    $u = trim((string) $u);
    if (!preg_match('/^[A-Za-z0-9._@+-]{3,80}$/', $u)) throw new UserError('Usernames are 3–80 characters: letters, numbers, and . _ @ + -');
    return $u;
}

function create_admin($data, $role = 'admin') {
    $username = clean_username($data['username'] ?? '');
    if (q1('SELECT id FROM admins WHERE username = ?', [$username])) throw new UserError('That username is already in use.');
    validate_password($data['password'] ?? '');
    $handle = unique_handle(!empty($data['handle']) ? $data['handle'] : $username);
    $plan = $role === 'master' ? 'enterprise' : (isset(PLANS[$data['plan'] ?? '']) ? $data['plan'] : 'basic');
    $teamOf = !empty($data['team_of']) ? (int) $data['team_of'] : null;
    $email = !empty($data['email']) ? $data['email'] : (filter_var($username, FILTER_VALIDATE_EMAIL) ? strtolower($username) : null);
    $mustChange = $role === 'master' ? 0 : (array_key_exists('must_change_pw', $data) ? (int) $data['must_change_pw'] : 1);
    qexec('INSERT INTO admins (email, source, plan, team_of, handle, username, display_name, org, phone, password_hash, role, slug, status, expires_at, notes, must_change_pw, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$email, $data['source'] ?? 'master', $plan, $teamOf, $handle,
        $username, trim($data['display_name'] ?? '') ?: $username, trim($data['org'] ?? ''),
        $role === 'master' ? MASTER_PHONE : preg_replace('/[^\d+]/', '', $data['phone'] ?? ''),
        password_hash($data['password'], PASSWORD_DEFAULT), $role, new_slug(), 'active',
        !empty($data['expires_at']) ? (int) $data['expires_at'] : null, trim($data['notes'] ?? ''),
        $mustChange, now_ms()]);
    $id = (int) db()->lastInsertId();
    ensure_host_folder($handle);
    return $id;
}

function format_phone($p) {
    $d = preg_replace('/\D/', '', (string) $p);
    if (strlen($d) === 11 && $d[0] === '1') $d = substr($d, 1);
    return strlen($d) === 10 ? sprintf('(%s) %s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6)) : $p;
}

/* ------------------------------------------- two-factor (authenticator app) */

function b32_encode($bin) {
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 5) as $chunk) $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}
function b32_decode($s) {
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}
function totp_new_secret() { return b32_encode(random_bytes(20)); }
function totp_code($secret, $step) {
    $h = hash_hmac('sha1', pack('N2', 0, $step), b32_decode($secret), true);
    $o = ord($h[19]) & 0x0f;
    $v = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($v % 1000000), 6, '0', STR_PAD_LEFT);
}
/** Returns the matching time-step (to block re-use), or false. Allows ±30 s of clock drift. */
function totp_verify($secret, $code, $lastStep = 0) {
    $code = preg_replace('/\D/', '', (string) $code);
    if (strlen($code) !== 6) return false;
    $now = (int) floor(time() / 30);
    for ($d = -1; $d <= 1; $d++) {
        $step = $now + $d;
        if ($step > $lastStep && hash_equals(totp_code($secret, $step), $code)) return $step;
    }
    return false;
}
function totp_uri($secret, $username) {
    return 'otpauth://totp/' . rawurlencode(APP_NAME . ':' . $username) . '?secret=' . $secret . '&issuer=' . rawurlencode(APP_NAME) . '&digits=6&period=30';
}
function new_backup_codes() {
    $plain = [];
    for ($i = 0; $i < 8; $i++) $plain[] = strtolower(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
    return $plain;
}
function use_backup_code($a, $code) {
    $code = strtolower(trim($code));
    $list = json_decode($a['backup_codes'] ?: '[]', true) ?: [];
    foreach ($list as $i => $hash) {
        if (password_verify($code, $hash)) {
            array_splice($list, $i, 1);
            qexec('UPDATE admins SET backup_codes = ? WHERE id = ?', [json_encode($list), $a['id']]);
            return true;
        }
    }
    return false;
}
