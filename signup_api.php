<?php
/* Public sign-up actions (no login): plan info, create an account, ask for a sales meeting. Included by api.php. */

$b = billing_get();
$planCard = function ($k) use ($b) {
    $p = PLANS[$k];
    return ['key' => $k, 'label' => $p['label'], 'price' => plan_price($k, $b), 'players' => $p['players'], 'quizzes' => $p['quizzes'],
        'branding' => $p['branding'], 'ppt' => $p['ppt'], 'export' => $p['export'], 'team' => $p['team']];
};

switch ($action) {

case 'signup_info':
    $me = current_admin();
    $mine = null;
    if ($me && !is_master($me) && empty($me['team_of'])) {
        $pk = plan_key($me);
        $mine = ['name' => $me['display_name'], 'plan' => $pk, 'planUntil' => $me['plan_until'] ? (int) $me['plan_until'] : null,
            'invoices' => array_map(function ($i) { return ['number' => $i['number'], 'plan' => $i['plan'], 'url' => invoice_url($i)]; },
                qall("SELECT * FROM invoices WHERE admin_id = ? AND status = 'unpaid' ORDER BY id DESC", [$me['id']]))];
    }
    out(['open' => (bool) $b['signupsOpen'], 'plans' => array_map($planCard, array_keys(PLANS)), 'salesLink' => $b['salesLink'],
        'business' => $b['businessName'], 'me' => $mine, 'signedInOther' => $me && !$mine ? true : false]);

case 'signup':
    if (!$b['signupsOpen']) fail('New sign-ups are closed right now. Please contact us.');
    if (trim((string) in('website')) !== '') fail('Please try again.');            // honeypot: real people never fill this in
    $plan = (string) in('plan', 'free');
    if (!in_array($plan, ['free', 'basic', 'pro'], true)) fail('Choose a plan.');
    $name = trim((string) in('name'));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) fail('Please enter your name.');
    $email = clean_email(in('email'));
    $org = mb_substr(trim((string) in('org')), 0, 120);
    if (in('password') !== in('password2')) fail('The two passwords don\'t match.');
    validate_password((string) in('password'));
    if (in('agree') !== '1') fail('Please accept the terms to continue.');
    if (q1('SELECT id FROM admins WHERE username = ? COLLATE NOCASE OR email = ? COLLATE NOCASE', [$email, $email]))
        fail('There is already an account for that email. Sign in instead, or ask us to reset your password.');
    signup_rate_limit('signup', 30);   // a whole room on one venue Wi-Fi shares an address
    $id = tx(function () use ($name, $email, $org, $plan) {
        return create_admin(['plan' => 'free', 'username' => $email, 'email' => $email, 'password' => (string) in('password'),
            'display_name' => $name, 'org' => $org, 'handle' => in('handle') ?: $email, 'must_change_pw' => 0, 'source' => 'signup',
            'notes' => 'Signed up online' . ($plan !== 'free' ? ' for ' . PLANS[$plan]['label'] : '') . ' on ' . date('M j, Y')]);
    });
    $a = admin_by_id($id);
    $res = ['plan' => $plan, 'handle' => $a['handle'], 'login' => app_base_url() . 'login.php', 'joinLink' => app_base_url() . $a['handle'] . '/'];
    if ($plan === 'free') {
        $sent = send_mail($email, 'Welcome to ' . APP_NAME . '!',
            "Hi $name,\n\nYour free " . APP_NAME . " account is ready.\n\nSign in: {$res['login']}\nUsername: $email\nYour players' link: {$res['joinLink']}\n\n"
            . "The first time you sign in you'll set up an authenticator app (Google or Microsoft Authenticator) to keep your account safe.\n\nHave fun!\n— {$b['businessName']}",
            mail_html('Welcome!', '<p>Hi ' . h($name) . ',</p><p>Your free <b>' . h(APP_NAME) . '</b> account is ready.</p>'
                . '<p><a href="' . h($res['login']) . '" style="background:#7c3aed;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:bold">Sign in</a></p>'
                . '<p>Username: <b>' . h($email) . '</b><br>Your players\' link: ' . h($res['joinLink']) . '</p>'
                . '<p>The first time you sign in you\'ll set up an authenticator app (Google or Microsoft Authenticator) to keep your account safe.</p><p>Have fun!<br>— ' . h($b['businessName']) . '</p>'));
        $res['emailed'] = $sent === true;
        notify_owner('New free ' . APP_NAME . ' account: ' . $name, "$name ($email)" . ($org ? ", $org" : '') . " created a free account.");
    } else {
        $inv = invoice_create($a, $plan);
        $sent = invoice_email($inv);
        $res += ['invoice' => $inv['number'], 'invoiceUrl' => invoice_url($inv), 'amount' => money($inv['amount_cents']), 'emailed' => $sent === true];
        notify_owner('New ' . PLANS[$plan]['label'] . ' sign-up: ' . $name . ' (' . $inv['number'] . ')',
            "$name ($email)" . ($org ? ", $org" : '') . " signed up for " . PLANS[$plan]['label'] . ".\nInvoice {$inv['number']} for " . money($inv['amount_cents'])
            . ($sent === true ? ' was emailed to them.' : " could NOT be emailed: $sent"));
    }
    out($res);

case 'sales_request':
    if (trim((string) in('website')) !== '') fail('Please try again.');
    $name = trim((string) in('name'));
    if (mb_strlen($name) < 2) fail('Please enter your name.');
    $email = clean_email(in('email'));
    $org = trim((string) in('org'));
    if ($org === '') fail('Please enter your institution or company.');
    signup_rate_limit('sales', 10);
    $row = ['name' => mb_substr($name, 0, 80), 'org' => mb_substr($org, 0, 120), 'email' => $email,
        'phone' => mb_substr(preg_replace('/[^\d+()\s.-]/', '', (string) in('phone')), 0, 30), 'size' => mb_substr(trim((string) in('size')), 0, 60),
        'times' => mb_substr(trim((string) in('times')), 0, 300), 'message' => mb_substr(trim((string) in('message')), 0, 2000)];
    qexec('INSERT INTO leads (name, org, email, phone, size, times, message, status, created_at, ip) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$row['name'], $row['org'], $row['email'], $row['phone'], $row['size'], $row['times'], $row['message'], 'new', now_ms(), client_ip()]);
    $sent = send_mail($email, 'We got your ' . APP_NAME . ' Enterprise request',
        "Hi {$row['name']},\n\nThanks for your interest in " . APP_NAME . " Enterprise for {$row['org']}. We'll reach out within one business day to set up a meeting"
        . ($row['times'] ? " (you suggested: {$row['times']})" : '') . ".\n" . ($b['salesLink'] ? "\nYou can also pick a time right away: {$b['salesLink']}\n" : '')
        . "\n— {$b['businessName']}");
    notify_owner('Enterprise meeting request: ' . $row['org'],
        "Name: {$row['name']}\nInstitution: {$row['org']}\nEmail: {$row['email']}\nPhone: {$row['phone']}\nHosts / size: {$row['size']}\nPreferred times: {$row['times']}\n\n{$row['message']}");
    out(['emailed' => $sent === true, 'salesLink' => $b['salesLink']]);
}
