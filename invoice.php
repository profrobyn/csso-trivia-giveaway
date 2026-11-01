<?php
/* Printable invoice. Opened from the emailed link (number + secret token) or by the master admin. */
require_once __DIR__ . '/lib.php';
$inv = q1('SELECT * FROM invoices WHERE number = ?', [(string) ($_GET['n'] ?? '')]);
$ok = $inv && hash_equals($inv['token'], (string) ($_GET['k'] ?? ''));
if (!$ok && $inv) { $me = current_admin(); $ok = $me && is_master($me); }
if (!$ok) { http_response_code(404); echo '<!doctype html><meta charset="utf-8"><title>Invoice not found</title><p style="font-family:sans-serif;padding:40px">Invoice not found. Please use the link from your email.</p>'; exit; }
$b = billing_get();
$label = PLANS[$inv['plan']]['label'];
$p = PLANS[$inv['plan']];
$d = function ($ms) { return $ms ? date('F j, Y', (int) ($ms / 1000)) : '—'; };
$periodStart = $inv['period_start'] ?: null; $periodEnd = $inv['period_end'] ?: null;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Invoice <?= h($inv['number']) ?> — <?= h($b['businessName']) ?></title>
<link rel="stylesheet" href="assets/vendor/fonts.css">
<style>
  :root { --ink:#1d1b33; --muted:#6b6886; --line:#e6e3f3; --accent:#7c3aed; }
  * { box-sizing: border-box; }
  body { margin:0; background:#f1eff9; font-family:Inter, system-ui, sans-serif; color:var(--ink); }
  .bar { max-width:820px; margin:20px auto 0; display:flex; justify-content:flex-end; gap:8px; padding:0 16px; }
  .bar button, .bar a { font:600 14px Inter, sans-serif; border:0; border-radius:10px; padding:10px 16px; cursor:pointer; text-decoration:none; }
  .bar .pri { background:var(--accent); color:#fff; } .bar .sec { background:#fff; color:var(--ink); border:1px solid var(--line); }
  .sheet { position:relative; max-width:820px; margin:12px auto 40px; background:#fff; border-radius:18px; box-shadow:0 8px 40px rgba(40,20,90,.12); padding:48px 52px; overflow:hidden; }
  .top { display:flex; justify-content:space-between; gap:24px; flex-wrap:wrap; }
  .brand { font-family:Fredoka, Inter, sans-serif; font-size:30px; font-weight:700; display:flex; align-items:center; gap:10px; }
  .badge { width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,#ff4fd8,#7c3aed); display:grid; place-items:center; color:#fff; font-size:24px; }
  h1 { font-family:Fredoka, Inter, sans-serif; font-size:40px; margin:0; letter-spacing:.02em; color:var(--accent); text-align:right; }
  .meta { text-align:right; color:var(--muted); font-size:14px; line-height:1.7; }
  .meta b { color:var(--ink); }
  .cols { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin:36px 0 28px; font-size:14px; line-height:1.6; }
  .lbl { text-transform:uppercase; letter-spacing:.12em; font-size:11px; font-weight:700; color:var(--muted); margin-bottom:6px; }
  table { width:100%; border-collapse:collapse; font-size:15px; }
  th { text-align:left; font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); border-bottom:2px solid var(--line); padding:10px 8px; }
  td { padding:14px 8px; border-bottom:1px solid var(--line); vertical-align:top; }
  .r { text-align:right; white-space:nowrap; }
  .small { color:var(--muted); font-size:13px; }
  .total td { border:0; font-size:20px; font-weight:800; padding-top:18px; }
  .pay { margin-top:30px; background:#f7f5ff; border:1px solid #e3dcff; border-radius:14px; padding:18px 20px; }
  .pay p { margin:6px 0 0; white-space:pre-line; line-height:1.6; }
  .foot { margin-top:30px; color:var(--muted); font-size:13px; text-align:center; }
  .stamp { position:absolute; top:150px; right:-60px; transform:rotate(18deg); font:800 46px Fredoka, Inter, sans-serif; letter-spacing:.12em; padding:6px 90px; opacity:.9; }
  .stamp.paid { color:#047857; background:rgba(16,185,129,.14); border:3px solid #10b981; }
  .stamp.void { color:#9f1239; background:rgba(244,63,94,.12); border:3px solid #f43f5e; }
  @media (max-width:640px) { .sheet { padding:28px 20px; border-radius:0; } .cols { grid-template-columns:1fr; } h1, .meta { text-align:left; } .stamp { font-size:30px; top:110px; } }
  @media print { body { background:#fff; } .bar { display:none; } .sheet { box-shadow:none; margin:0; max-width:none; border-radius:0; padding:24px 8px; } }
</style>
</head>
<body>
<div class="bar"><button class="pri" onclick="print()">🖨️ Print / save as PDF</button></div>
<div class="sheet">
  <?php if ($inv['status'] === 'paid'): ?><div class="stamp paid">PAID</div><?php elseif ($inv['status'] === 'void'): ?><div class="stamp void">VOID</div><?php endif; ?>
  <div class="top">
    <div>
      <div class="brand"><span class="badge">⚡</span><?= h($b['businessName']) ?></div>
      <div class="small" style="margin-top:8px;white-space:pre-line"><?= h(trim($b['businessAddress'])) ?><?= $b['businessEmail'] ? "\n" . h($b['businessEmail']) : '' ?></div>
    </div>
    <div>
      <h1>INVOICE</h1>
      <div class="meta">
        Invoice # <b><?= h($inv['number']) ?></b><br>
        Date <b><?= $d($inv['created_at']) ?></b><br>
        <?php if ($inv['status'] === 'paid'): ?>Paid <b><?= $d($inv['paid_at']) ?></b><?php else: ?>Due <b><?= $d($inv['due_at']) ?></b><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="cols">
    <div><div class="lbl">Bill to</div><b><?= h($inv['name']) ?></b><?= $inv['org'] ? '<br>' . h($inv['org']) : '' ?><br><?= h($inv['email']) ?></div>
    <div><div class="lbl">Status</div><b style="font-size:18px;color:<?= $inv['status'] === 'paid' ? '#047857' : ($inv['status'] === 'void' ? '#9f1239' : '#b45309') ?>">
      <?= $inv['status'] === 'paid' ? 'Paid — thank you!' : ($inv['status'] === 'void' ? 'Cancelled' : 'Awaiting payment') ?></b></div>
  </div>

  <table>
    <thead><tr><th>Description</th><th class="r">Amount</th></tr></thead>
    <tbody>
      <tr><td><b><?= h(APP_NAME . ' ' . $label) ?> plan — 1 year</b>
        <div class="small"><?= $p['players'] ? number_format($p['players']) . ' players per quiz' : 'Unlimited players' ?> ·
          <?= $p['quizzes'] ? $p['quizzes'] . ' quizzes a month' : 'unlimited quizzes' ?><?= $p['branding'] ? ' · branding, PowerPoint and Excel downloads' : '' ?></div>
        <div class="small">Account: <?= h($inv['email']) ?><?= $periodStart ? ' · ' . $d($periodStart) . ' – ' . $d($periodEnd) : ' · 12 months from the date payment is received' ?></div></td>
        <td class="r"><?= money($inv['amount_cents']) ?></td></tr>
      <tr class="total"><td class="r">Total<?= $inv['status'] === 'paid' ? ' paid' : ' due' ?> (USD)</td><td class="r"><?= money($inv['amount_cents']) ?></td></tr>
    </tbody>
  </table>

  <?php if ($inv['status'] === 'unpaid'): ?>
  <div class="pay"><div class="lbl">How to pay</div><p><?= h($b['paymentInstructions']) ?></p>
    <p class="small" style="margin-top:10px">Please include invoice number <b><?= h($inv['number']) ?></b> with your payment.</p></div>
  <?php endif; ?>

  <div class="foot">Thank you for using <?= h(APP_NAME) ?>! Questions? <?= $b['businessEmail'] ? 'Email ' . h($b['businessEmail']) . '.' : 'Just reply to the invoice email.' ?></div>
</div>
</body>
</html>
