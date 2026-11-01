<?php
/* RizQuiz sign-ups, invoices, sales requests and email. Loaded by lib.php. */

const PLAN_PRICES_DEFAULT = ['basic' => 50, 'pro' => 100];   // US dollars per year

/** v8: self sign-up, invoices, sales meeting requests, an email log. */
function migrate_v8($pdo) {
    $cols = array_column($pdo->query('PRAGMA table_info(admins)')->fetchAll(), 'name');
    if (!in_array('email', $cols, true)) $pdo->exec('ALTER TABLE admins ADD COLUMN email TEXT');
    if (!in_array('plan_until', $cols, true)) $pdo->exec('ALTER TABLE admins ADD COLUMN plan_until INTEGER');
    if (!in_array('source', $cols, true)) $pdo->exec("ALTER TABLE admins ADD COLUMN source TEXT DEFAULT 'master'");
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT UNIQUE, token TEXT NOT NULL,
        admin_id INTEGER, plan TEXT NOT NULL, amount_cents INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'unpaid', name TEXT, org TEXT, email TEXT,
        created_at INTEGER, due_at INTEGER, paid_at INTEGER, sent_at INTEGER, period_start INTEGER, period_end INTEGER, notes TEXT)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_invoices_admin ON invoices(admin_id)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, org TEXT, email TEXT, phone TEXT, size TEXT,
        times TEXT, message TEXT, status TEXT NOT NULL DEFAULT 'new', created_at INTEGER, ip TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, to_addr TEXT, subject TEXT, ok INTEGER, error TEXT, created_at INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS signup_hits (ip TEXT, kind TEXT, created_at INTEGER)");
    $pdo->exec('PRAGMA user_version = 8');
}

/* ------------------------------------------------------------ billing settings (master only) */

function billing_defaults() {
    return [
        'signupsOpen' => true,
        'businessName' => APP_NAME, 'businessAddress' => '', 'businessEmail' => '',
        'priceBasic' => PLAN_PRICES_DEFAULT['basic'], 'pricePro' => PLAN_PRICES_DEFAULT['pro'],
        'dueDays' => 30, 'invoicePrefix' => 'RQ',
        'paymentInstructions' => "Pay by check made out to " . APP_NAME . ", or contact us to pay another way.\nPlease write the invoice number on your payment.",
        'salesLink' => '', 'notifyEmail' => '',
        'smtpHost' => '', 'smtpPort' => 587, 'smtpSecure' => 'tls', 'smtpUser' => '', 'smtpPass' => '',
        'fromEmail' => '', 'fromName' => APP_NAME,
    ];
}
function billing_get() {
    $v = qval("SELECT v FROM settings WHERE k = 'sys:billing'");
    $saved = $v ? json_decode($v, true) : [];
    return array_merge(billing_defaults(), is_array($saved) ? $saved : []);
}
/** What the browser may see: never the SMTP password. */
function billing_public($b = null) {
    $b = $b ?: billing_get();
    $out = $b; unset($out['smtpPass']);
    $out['smtpPassSet'] = $b['smtpPass'] !== '';
    return $out;
}
function billing_save($in) {
    $b = billing_get(); $d = billing_defaults();
    foreach ($d as $k => $def) {
        if (!array_key_exists($k, $in)) continue;
        $v = $in[$k];
        if ($k === 'smtpPass' && $v === '') continue;            // blank = keep the saved password
        if (is_bool($def)) $v = filter_var($v, FILTER_VALIDATE_BOOLEAN);
        elseif (is_int($def)) $v = max(0, (int) $v);
        else $v = trim((string) $v);
        $b[$k] = $v;
    }
    if (!empty($in['smtpPassClear'])) $b['smtpPass'] = '';
    foreach (['businessEmail', 'notifyEmail', 'fromEmail'] as $k)
        if ($b[$k] !== '' && !filter_var($b[$k], FILTER_VALIDATE_EMAIL)) throw new UserError('“' . $b[$k] . '” is not a valid email address.');
    if ($b['salesLink'] !== '' && !preg_match('#^https?://#i', $b['salesLink'])) throw new UserError('The meeting booking link must start with https://');
    if (!in_array($b['smtpSecure'], ['tls', 'ssl', 'none'], true)) $b['smtpSecure'] = 'tls';
    $b['smtpPort'] = $b['smtpPort'] ?: 587;
    $b['priceBasic'] = max(0, (int) $b['priceBasic']); $b['pricePro'] = max(0, (int) $b['pricePro']);
    $b['dueDays'] = min(365, max(0, (int) $b['dueDays']));
    $b['invoicePrefix'] = preg_replace('/[^A-Za-z0-9-]/', '', $b['invoicePrefix']) ?: 'RQ';
    qexec("INSERT INTO settings (k, v) VALUES ('sys:billing', ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v", [json_encode($b)]);
    return $b;
}
function plan_price($plan, $b = null) {
    $b = $b ?: billing_get();
    return $plan === 'basic' ? (int) $b['priceBasic'] : ($plan === 'pro' ? (int) $b['pricePro'] : 0);
}
function money($cents) { return '$' . number_format($cents / 100, 2); }

/* ------------------------------------------------------------ plans that run out */

/** Paid plans bought through an invoice last a year; afterwards the account quietly drops back to Free. */
function plan_expired($a) {
    return $a && in_array($a['plan'] ?? '', ['basic', 'pro'], true) && !empty($a['plan_until']) && (int) $a['plan_until'] < now_ms();
}

/* ------------------------------------------------------------ invoices */

function invoice_create($admin, $plan) {
    if (!in_array($plan, ['basic', 'pro'], true)) throw new UserError('Choose the Basic or Pro plan.');
    $b = billing_get();
    // one open invoice per account and plan is enough
    $open = q1("SELECT * FROM invoices WHERE admin_id = ? AND plan = ? AND status = 'unpaid'", [$admin['id'], $plan]);
    if ($open) return $open;
    $now = now_ms();
    qexec('INSERT INTO invoices (token, admin_id, plan, amount_cents, status, name, org, email, created_at, due_at) VALUES (?,?,?,?,?,?,?,?,?,?)', [
        bin2hex(random_bytes(16)), $admin['id'], $plan, plan_price($plan, $b) * 100, 'unpaid',
        $admin['display_name'], $admin['org'], $admin['email'] ?: (filter_var($admin['username'], FILTER_VALIDATE_EMAIL) ? $admin['username'] : ''),
        $now, $now + (int) $b['dueDays'] * 86400000]);
    $id = (int) db()->lastInsertId();
    qexec('UPDATE invoices SET number = ? WHERE id = ?', [$b['invoicePrefix'] . '-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);
    return q1('SELECT * FROM invoices WHERE id = ?', [$id]);
}
function invoice_url($inv) { return app_base_url() . 'invoice.php?n=' . rawurlencode($inv['number']) . '&k=' . $inv['token']; }

/** Mark paid: the account gets the plan for one year (added on top of any time it still has). */
function invoice_mark_paid($inv) {
    if ($inv['status'] === 'paid') return $inv;
    $a = admin_by_id($inv['admin_id']);
    $now = now_ms();
    $from = $now;
    if ($a && $a['plan'] === $inv['plan'] && !empty($a['plan_until']) && (int) $a['plan_until'] > $now) $from = (int) $a['plan_until'];
    $until = strtotime('+1 year', (int) ($from / 1000)) * 1000;
    tx(function () use ($inv, $a, $now, $from, $until) {
        qexec("UPDATE invoices SET status = 'paid', paid_at = ?, period_start = ?, period_end = ? WHERE id = ?", [$now, $from, $until, $inv['id']]);
        if ($a && !is_master($a) && $a['plan'] !== 'enterprise') qexec('UPDATE admins SET plan = ?, plan_until = ? WHERE id = ?', [$inv['plan'], $until, $a['id']]);
    });
    return q1('SELECT * FROM invoices WHERE id = ?', [$inv['id']]);
}

function invoice_email($inv) {
    $b = billing_get();
    $label = PLANS[$inv['plan']]['label'];
    $url = invoice_url($inv);
    $due = date('F j, Y', (int) ($inv['due_at'] / 1000));
    $text = "Hi {$inv['name']},\n\nThank you for choosing " . APP_NAME . " {$label}!\n\n"
        . "Invoice: {$inv['number']}\nPlan: {$label} — 1 year\nAmount due: " . money($inv['amount_cents']) . "\nDue: {$due}\n\n"
        . "HOW TO PAY\n{$b['paymentInstructions']}\n\n"
        . "View or print your invoice: {$url}\n\n"
        . "Your account is ready to use on the Free plan now. It switches to {$label} as soon as your payment is received.\n\n"
        . "— {$b['businessName']}" . ($b['businessEmail'] ? "\n{$b['businessEmail']}" : '');
    $html = mail_html("Invoice {$inv['number']}", '<p>Hi ' . h($inv['name']) . ',</p><p>Thank you for choosing <b>' . h(APP_NAME . ' ' . $label) . '</b>!</p>'
        . '<table cellpadding="6" style="border-collapse:collapse;margin:12px 0">'
        . '<tr><td style="color:#666">Invoice</td><td><b>' . h($inv['number']) . '</b></td></tr>'
        . '<tr><td style="color:#666">Plan</td><td>' . h($label) . ' — 1 year</td></tr>'
        . '<tr><td style="color:#666">Amount due</td><td><b style="font-size:18px">' . money($inv['amount_cents']) . '</b></td></tr>'
        . '<tr><td style="color:#666">Due</td><td>' . h($due) . '</td></tr></table>'
        . '<h3 style="margin:18px 0 6px">How to pay</h3><p style="white-space:pre-line">' . h($b['paymentInstructions']) . '</p>'
        . '<p style="margin:22px 0"><a href="' . h($url) . '" style="background:#7c3aed;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:bold">View / print invoice</a></p>'
        . '<p>Your account is ready on the Free plan now. It switches to ' . h($label) . ' as soon as your payment is received.</p>'
        . '<p>— ' . h($b['businessName']) . ($b['businessEmail'] ? '<br>' . h($b['businessEmail']) : '') . '</p>');
    $r = send_mail($inv['email'], 'Your ' . APP_NAME . ' ' . $label . ' invoice ' . $inv['number'], $text, $html);
    if ($r === true) qexec('UPDATE invoices SET sent_at = ? WHERE id = ?', [now_ms(), $inv['id']]);
    return $r;
}

/* ------------------------------------------------------------ sign-up helpers */

function client_ip() { return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64); }
/** Simple abuse guard for the public forms: N per hour per IP address. */
function signup_rate_limit($kind, $max) {
    $ip = client_ip(); $hour = now_ms() - 3600000;
    qexec('DELETE FROM signup_hits WHERE created_at < ?', [now_ms() - 86400000]);
    if ((int) qval('SELECT COUNT(*) FROM signup_hits WHERE ip = ? AND kind = ? AND created_at > ?', [$ip, $kind, $hour]) >= $max)
        throw new UserError('Too many requests from this network. Please try again in an hour.');
    qexec('INSERT INTO signup_hits (ip, kind, created_at) VALUES (?,?,?)', [$ip, $kind, now_ms()]);
}
function clean_email($e) {
    $e = trim((string) $e);
    if (!filter_var($e, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $e)) throw new UserError('Please enter a valid email address.');
    return strtolower($e);
}
/** The RizQuiz folder's web address. Uses the host's saved server address when this page was opened as localhost. */
function app_base_url() { return base_url(); }

function notify_owner($subject, $text) {
    $b = billing_get();
    $to = $b['notifyEmail'] ?: $b['businessEmail'];
    if ($to) send_mail($to, $subject, $text . "\n\nOpen RizQuiz → Billing: " . app_base_url() . 'billing.php');
}

/* ------------------------------------------------------------ email */

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function mail_html($title, $body) {
    return '<!doctype html><html><body style="margin:0;background:#f4f2fb;font-family:Segoe UI,Arial,sans-serif;color:#1d1b33">'
        . '<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">'
        . '<div style="background:linear-gradient(135deg,#ff4fd8,#7c3aed);color:#fff;padding:18px 24px;font-size:20px;font-weight:bold">⚡ ' . h(APP_NAME) . ' · ' . h($title) . '</div>'
        . '<div style="padding:22px 24px;font-size:15px;line-height:1.55">' . $body . '</div></div></body></html>';
}
function mail_ready($b = null) { $b = $b ?: billing_get(); return $b['smtpHost'] !== '' && ($b['fromEmail'] !== '' || $b['smtpUser'] !== ''); }

/** Send an email. Uses the SMTP server from Billing settings; falls back to PHP mail(). Returns true or an error message. */
function send_mail($to, $subject, $text, $html = null) {
    $b = billing_get();
    $to = trim((string) $to);
    $subject = preg_replace('/[\r\n]+/', ' ', (string) $subject);
    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new Exception('No valid email address to send to.');
        $from = $b['fromEmail'] ?: $b['smtpUser'] ?: ($b['businessEmail'] ?: 'no-reply@' . preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName = preg_replace('/[\r\n"]+/', '', $b['fromName'] ?: APP_NAME);
        $boundary = 'rq' . bin2hex(random_bytes(8));
        $headers = [
            'From: "' . $fromName . '" <' . $from . '>',
            'Reply-To: ' . ($b['businessEmail'] ?: $from),
            'MIME-Version: 1.0',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (explode('@', $from)[1] ?? 'rizquiz') . '>',
        ];
        if ($html) {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
                . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$boundary--\r\n";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($text));
        }
        $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        if ($b['smtpHost'] !== '') smtp_send($b, $from, $to, $encSubject, $headers, $body);
        elseif (!@mail($to, $encSubject, $body, implode("\r\n", $headers))) throw new Exception('Email is not set up yet. Add your email (SMTP) settings on the Billing page.');
        qexec('INSERT INTO mail_log (to_addr, subject, ok, error, created_at) VALUES (?,?,1,NULL,?)', [$to, $subject, now_ms()]);
        return true;
    } catch (Throwable $e) {
        qexec('INSERT INTO mail_log (to_addr, subject, ok, error, created_at) VALUES (?,?,0,?,?)', [$to, $subject, mb_substr($e->getMessage(), 0, 300), now_ms()]);
        return $e->getMessage();
    }
}

function smtp_send($b, $from, $to, $subject, $headers, $body) {
    $host = $b['smtpHost']; $port = (int) $b['smtpPort'] ?: 587; $sec = $b['smtpSecure'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client(($sec === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new Exception("Could not connect to the email server $host:$port ($errstr).");
    stream_set_timeout($fp, 20);
    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) { $data .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
        return $data;
    };
    $cmd = function ($c, $expect) use ($fp, $read) {
        if ($c !== null) fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int) substr($r, 0, 3), (array) $expect, true)) {
            $shown = $c !== null && stripos($c, 'AUTH') !== 0 && strlen($c) < 80 ? $c : 'the last step';
            throw new Exception('The email server refused ' . $shown . ': ' . trim($r));
        }
        return $r;
    };
    try {
        $cmd(null, 220);
        $ehloHost = preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
        $ehlo = $cmd('EHLO ' . $ehloHost, 250);
        if ($sec === 'tls') {
            $cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new Exception('Could not start a secure (TLS) connection with the email server.');
            $ehlo = $cmd('EHLO ' . $ehloHost, 250);
        }
        if ($b['smtpUser'] !== '') {
            $cmd('AUTH LOGIN', 334);
            $cmd(base64_encode($b['smtpUser']), 334);
            try { $cmd(base64_encode($b['smtpPass']), 235); }
            catch (Exception $e) { throw new Exception('The email server did not accept the username or password. ' . preg_replace('/^.*?: /', '', $e->getMessage())); }
        }
        $cmd('MAIL FROM:<' . $from . '>', 250);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', 354);
        $msg = implode("\r\n", array_merge(['To: <' . $to . '>', 'Subject: ' . $subject], $headers)) . "\r\n\r\n" . $body;
        $msg = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $msg));
        $cmd(str_replace("\n", "\r\n", $msg) . "\r\n.", 250);
        fwrite($fp, "QUIT\r\n");
    } finally { fclose($fp); }
}
