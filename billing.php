<?php
/* Master admin: invoices, Enterprise meeting requests, prices, payment instructions and email settings. */
require_once __DIR__ . '/lib.php';
$me = current_admin();
if (!$me) { header('Location: login.php'); exit; }
if (!is_master($me)) { header('Location: setup.php'); exit; }
$navActive = 'billing';
$pageTitle = 'Billing — ' . APP_NAME;
?><!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/partials/head.php'; ?></head>
<body>
<div class="blobs"><i></i><i></i><i></i></div>
<?php include __DIR__ . '/partials/admin_top.php'; ?>

<main class="p-3 md:p-6 max-w-[1400px] mx-auto space-y-5 pb-16">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <p class="chip chip-gold"><i class="fa-solid fa-crown"></i> Master admin</p>
      <h1 class="font-display text-4xl font-bold mt-2">Billing &amp; sign-ups</h1>
      <p class="text-muted">People sign up at <a id="suLink" class="font-bold text-cyan-200 hover:underline" target="_blank"></a>
        <button class="btn btn-sm btn-ghost !py-0.5" onclick="B.copy()" title="Copy link"><i class="fa-regular fa-copy"></i></button></p>
    </div>
    <div class="flex gap-2"><button class="btn" onclick="B.openNew()"><i class="fa-solid fa-file-circle-plus"></i> New invoice</button></div>
  </div>

  <div id="mailWarn" class="hidden rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3" style="background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.5)">
    <p><b>⚠️ Email isn't set up yet.</b> Sign-ups still work and every invoice has its own link, but nothing is emailed until you add your email settings.</p>
    <button class="btn btn-gold btn-sm" onclick="B.tab('settings'); document.getElementById('bSmtpHost').focus()">Set up email</button>
  </div>

  <div id="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3"></div>

  <nav class="flex flex-wrap gap-1 glass-2 !rounded-xl p-1 w-fit" id="tabs">
    <button data-t="invoices" class="btn btn-sm" onclick="B.tab('invoices')"><i class="fa-solid fa-file-invoice-dollar"></i> Invoices</button>
    <button data-t="leads" class="btn btn-sm" onclick="B.tab('leads')"><i class="fa-solid fa-calendar-check"></i> Meeting requests <span id="leadBadge" class="chip chip-red !py-0 !px-1.5 !text-[10px] hidden"></span></button>
    <button data-t="settings" class="btn btn-sm" onclick="B.tab('settings')"><i class="fa-solid fa-gear"></i> Settings</button>
    <button data-t="mail" class="btn btn-sm" onclick="B.tab('mail')"><i class="fa-solid fa-envelope"></i> Email log</button>
  </nav>

  <!-- invoices -->
  <section data-p="invoices" class="card space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="font-display text-xl font-bold">Invoices</h3>
      <div class="flex gap-2"><select id="invFilter" class="field !w-auto !py-2" onchange="B.render()">
        <option value="unpaid">Waiting for payment</option><option value="paid">Paid</option><option value="void">Cancelled</option><option value="">All</option></select>
        <input id="invSearch" class="field !w-52 !py-2" placeholder="Search…" oninput="B.render()"></div>
    </div>
    <div class="overflow-x-auto"><table class="w-full text-sm">
      <thead class="text-muted uppercase text-[11px] tracking-wider text-left"><tr>
        <th class="p-2">Invoice</th><th class="p-2">Customer</th><th class="p-2">Plan</th><th class="p-2 text-right">Amount</th><th class="p-2">Dates</th><th class="p-2">Status</th><th class="p-2"></th></tr></thead>
      <tbody id="invRows"></tbody></table></div>
    <p class="help">When a payment arrives, click <b>Mark paid</b>. The account switches to that plan for one year (added on top of any time it still has), and the customer gets a receipt email.</p>
  </section>

  <!-- leads -->
  <section data-p="leads" class="hidden card space-y-3">
    <h3 class="font-display text-xl font-bold">Enterprise meeting requests</h3>
    <div id="leadRows" class="grid lg:grid-cols-2 gap-3"></div>
    <p class="help">When an organization signs up, create its account on the <a href="master.php" class="underline">Accounts</a> page with the Enterprise plan. That person becomes the organization's master user.</p>
  </section>

  <!-- settings -->
  <section data-p="settings" class="hidden grid lg:grid-cols-2 gap-5">
    <div class="card space-y-4">
      <h3 class="font-display text-xl font-bold">🧾 Business &amp; prices</h3>
      <label class="flex items-center gap-3 cursor-pointer"><span class="toggle"><input type="checkbox" id="bOpen"><span></span></span><span class="font-semibold">Accept new sign-ups</span></label>
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="lbl">Business name (on invoices)</label><input id="bName" class="field"></div>
        <div><label class="lbl">Business email (replies go here)</label><input id="bEmail" type="email" class="field"></div>
        <div class="sm:col-span-2"><label class="lbl">Business address (optional)</label><textarea id="bAddr" rows="2" class="field"></textarea></div>
        <div><label class="lbl">Basic price ($ / year)</label><input id="bPBasic" type="number" min="0" class="field"></div>
        <div><label class="lbl">Pro price ($ / year)</label><input id="bPPro" type="number" min="0" class="field"></div>
        <div><label class="lbl">Payment due after (days)</label><input id="bDue" type="number" min="0" max="365" class="field"></div>
        <div><label class="lbl">Invoice number prefix</label><input id="bPrefix" class="field" maxlength="10"></div>
      </div>
      <div><label class="lbl">How to pay (shown on every invoice)</label>
        <textarea id="bPay" rows="5" class="field" placeholder="e.g. Pay online: https://paypal.me/yourname&#10;or mail a check to …&#10;or Zelle: you@example.com"></textarea>
        <p class="help">Write exactly what customers should do, e.g. a PayPal / Stripe / Square payment link, Zelle, or where to mail a check.</p></div>
      <div><label class="lbl">Enterprise meeting booking link (optional)</label><input id="bSales" class="field" placeholder="https://calendly.com/you/rizquiz or a Microsoft Bookings link">
        <p class="help">Shown on the sign-up page so organizations can pick a time right away. Without it, they fill in a request form.</p></div>
      <div><label class="lbl">Send me sign-up alerts at</label><input id="bNotify" type="email" class="field" placeholder="you@example.com"></div>
    </div>

    <div class="card space-y-4">
      <h3 class="font-display text-xl font-bold">✉️ Sending email</h3>
      <p class="text-sm text-muted">Use your email account's SMTP settings. Common ones:
        <b>Gmail</b> smtp.gmail.com · 587 · TLS (needs an app password).
        <b>Outlook / Microsoft 365</b> smtp.office365.com · 587 · TLS (SMTP sign-in must be allowed by IT).
        Many web hosts give you SMTP details too.</p>
      <div class="grid sm:grid-cols-3 gap-3">
        <div class="sm:col-span-2"><label class="lbl">SMTP server</label><input id="bSmtpHost" class="field" placeholder="smtp.gmail.com"></div>
        <div><label class="lbl">Port</label><input id="bSmtpPort" type="number" class="field"></div>
        <div><label class="lbl">Security</label><select id="bSmtpSec" class="field"><option value="tls">TLS (587)</option><option value="ssl">SSL (465)</option><option value="none">None</option></select></div>
        <div class="sm:col-span-2"><label class="lbl">Username</label><input id="bSmtpUser" class="field" autocomplete="off"></div>
        <div class="sm:col-span-3"><label class="lbl">Password</label><input id="bSmtpPass" type="password" class="field" autocomplete="new-password"><p class="help" id="bPassHelp"></p></div>
        <div class="sm:col-span-2"><label class="lbl">Send from (email)</label><input id="bFrom" type="email" class="field" placeholder="Usually the same as the username"></div>
        <div><label class="lbl">Sender name</label><input id="bFromName" class="field"></div>
      </div>
      <div class="flex flex-wrap gap-2 items-end">
        <div class="flex-1 min-w-[12rem]"><label class="lbl">Send a test to</label><input id="bTestTo" type="email" class="field"></div>
        <button class="btn" onclick="B.test()"><i class="fa-solid fa-paper-plane"></i> Save &amp; send test</button>
      </div>
    </div>
    <div class="lg:col-span-2 flex justify-end"><button class="btn btn-primary btn-lg" onclick="B.save()"><i class="fa-solid fa-floppy-disk"></i> Save settings</button></div>
  </section>

  <!-- mail log -->
  <section data-p="mail" class="hidden card space-y-3">
    <h3 class="font-display text-xl font-bold">Recent emails</h3>
    <div id="mailRows" class="space-y-1.5 text-sm"></div>
  </section>
</main>

<!-- new invoice -->
<div id="newModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(5,4,20,.75);backdrop-filter:blur(6px)">
  <div class="glass w-full max-w-lg p-6 space-y-4" style="background:rgba(20,15,55,.97)">
    <div class="flex items-center justify-between"><h3 class="font-display text-2xl font-bold">New invoice</h3><button class="btn btn-ghost btn-sm" onclick="B.closeNew()"><i class="fa-solid fa-xmark text-lg"></i></button></div>
    <div><label class="lbl">Account</label><select id="nAcct" class="field" onchange="B.fillEmail()"></select></div>
    <div><label class="lbl">Plan</label><select id="nPlan" class="field"><option value="basic">Basic</option><option value="pro">Pro</option></select></div>
    <div><label class="lbl">Email</label><input id="nEmail" type="email" class="field"></div>
    <label class="flex items-center gap-3 cursor-pointer"><span class="toggle"><input type="checkbox" id="nSend" checked><span></span></span><span class="font-semibold">Email it to them now</span></label>
    <div class="flex justify-end gap-2"><button class="btn" onclick="B.closeNew()">Cancel</button><button class="btn btn-primary" onclick="B.createInv()"><i class="fa-solid fa-file-circle-plus"></i> Create</button></div>
  </div>
</div>

<script>
window.B = (function () {
  const $ = id => document.getElementById(id);
  let D = null, accounts = [], cur = 'invoices';
  async function call(a, d = {}) { const r = await RQ.api(a, d); if (!r.ok) { RQ.toast(r.error, 'error'); if (r.code === 'AUTH') setTimeout(() => location.href = 'login.php', 1000); } return r; }
  const day = ms => ms ? new Date(ms).toLocaleDateString() : '—';
  const PL = { free: 'Free', basic: 'Basic', pro: 'Pro ✨', enterprise: 'Enterprise' };

  async function load() {
    const r = await call('master_billing'); if (!r.ok) return;
    D = r; fillSettings(); render();
  }
  function tab(t) {
    cur = t;
    document.querySelectorAll('[data-p]').forEach(s => s.classList.toggle('hidden', s.dataset.p !== t));
    document.querySelectorAll('#tabs button').forEach(b => b.className = 'btn btn-sm ' + (b.dataset.t === t ? 'btn-primary' : 'btn-ghost'));
    history.replaceState(null, '', '#' + t);
  }
  function render() {
    $('suLink').textContent = D.signupUrl.replace(/^https?:\/\//, ''); $('suLink').href = D.signupUrl;
    $('mailWarn').classList.toggle('hidden', D.mailReady);
    const inv = D.invoices, unpaid = inv.filter(i => i.status === 'unpaid');
    const sum = list => '$' + list.reduce((s, i) => s + parseFloat(i.amount.replace(/[$,]/g, '')), 0).toLocaleString(undefined, { minimumFractionDigits: 0 });
    const yearAgo = Date.now() - 365 * 864e5;
    const newLeads = D.leads.filter(l => l.status === 'new').length;
    $('stats').innerHTML = [['🧾', unpaid.length, `waiting for payment (${sum(unpaid)})`], ['💰', sum(inv.filter(i => i.status === 'paid' && i.paid > yearAgo)), 'paid in the last 12 months'],
      ['📅', newLeads, 'new meeting requests'], ['✉️', D.mailReady ? 'On' : 'Off', 'email sending']]
      .map(([e, n, l]) => `<div class="card flex items-center gap-3"><span class="text-3xl">${e}</span><div><p class="font-display text-3xl font-bold leading-none">${n}</p><p class="text-muted text-sm">${l}</p></div></div>`).join('');
    $('leadBadge').textContent = newLeads; $('leadBadge').classList.toggle('hidden', !newLeads);

    const f = $('invFilter').value, s = $('invSearch').value.toLowerCase();
    const rows = inv.filter(i => (!f || i.status === f) && (!s || [i.number, i.name, i.org, i.email].join(' ').toLowerCase().includes(s)));
    $('invRows').innerHTML = rows.map(i => `<tr class="border-t border-white/10 align-top">
      <td class="p-2"><a href="${RQ.esc(i.url)}" target="_blank" class="font-bold text-cyan-200 hover:underline">${RQ.esc(i.number)}</a></td>
      <td class="p-2"><p class="font-semibold">${RQ.esc(i.name)}</p><p class="text-xs text-muted">${RQ.esc(i.email)}${i.org ? ' · ' + RQ.esc(i.org) : ''}</p></td>
      <td class="p-2">${PL[i.plan]}</td><td class="p-2 text-right font-bold">${RQ.esc(i.amount)}</td>
      <td class="p-2 text-xs text-muted">Issued ${day(i.created)}<br>${i.status === 'paid' ? `Paid ${day(i.paid)}<br>Plan until ${day(i.until)}` : `Due ${day(i.due)}`}<br>${i.sent ? 'Emailed ' + day(i.sent) : '<span class="text-amber-300">Not emailed</span>'}</td>
      <td class="p-2">${i.status === 'paid' ? '<span class="chip chip-green">Paid</span>' : i.status === 'void' ? '<span class="chip">Cancelled</span>'
        : (i.due < Date.now() ? '<span class="chip chip-red">Overdue</span>' : '<span class="chip chip-gold">Unpaid</span>')}</td>
      <td class="p-2"><div class="flex flex-wrap gap-1 justify-end">
        ${i.status === 'unpaid' ? `<button class="btn btn-sm btn-go" onclick="B.paid(${i.id})"><i class="fa-solid fa-check"></i> Mark paid</button>
          <button class="btn btn-sm" onclick="B.resend(${i.id})" title="Email it again"><i class="fa-solid fa-paper-plane"></i></button>
          <button class="btn btn-sm btn-danger" onclick="B.voidInv(${i.id})" title="Cancel invoice"><i class="fa-solid fa-ban"></i></button>` : ''}
        <button class="btn btn-sm" onclick="B.copyLink(${i.id})" title="Copy invoice link"><i class="fa-regular fa-copy"></i></button></div></td></tr>`).join('')
      || `<tr><td colspan="7" class="p-6 text-center text-muted">No invoices here yet.</td></tr>`;

    $('leadRows').innerHTML = D.leads.map(l => `<div class="glass-2 p-4 space-y-2 ${l.status === 'new' ? 'ring-glow' : ''}">
      <div class="flex items-start justify-between gap-2"><div><p class="font-display text-lg font-bold">${RQ.esc(l.org)}</p><p class="text-sm">${RQ.esc(l.name)} · <a class="text-cyan-200 hover:underline" href="mailto:${RQ.esc(l.email)}">${RQ.esc(l.email)}</a>${l.phone ? ' · ' + RQ.esc(l.phone) : ''}</p></div>
        <select class="field !w-auto !py-1.5 !text-sm" onchange="B.lead(${l.id}, this.value)">${['new', 'contacted', 'won', 'closed'].map(s => `<option value="${s}" ${l.status === s ? 'selected' : ''}>${{ new: '🆕 New', contacted: '📞 Contacted', won: '🏆 Signed', closed: '✖ Closed' }[s]}</option>`).join('')}</select></div>
      <p class="text-xs text-muted">${day(l.created)} · ${RQ.esc(l.size)} hosts${l.times ? ' · prefers ' + RQ.esc(l.times) : ''}</p>
      ${l.message ? `<p class="text-sm whitespace-pre-line border-l-2 border-white/20 pl-2">${RQ.esc(l.message)}</p>` : ''}</div>`).join('')
      || '<p class="text-muted">No meeting requests yet.</p>';

    $('mailRows').innerHTML = D.mail.map(m => `<div class="glass-2 px-3 py-2 flex flex-wrap items-center gap-2">
      <span>${m.ok ? '✅' : '❌'}</span><span class="text-muted text-xs w-36">${new Date(m.at).toLocaleString()}</span><b>${RQ.esc(m.to)}</b><span class="text-muted">${RQ.esc(m.subject)}</span>
      ${m.error ? `<span class="text-rose-300 text-xs w-full">${RQ.esc(m.error)}</span>` : ''}</div>`).join('') || '<p class="text-muted">Nothing sent yet.</p>';
  }
  function fillSettings() {
    const b = D.billing;
    $('bOpen').checked = b.signupsOpen; $('bName').value = b.businessName; $('bEmail').value = b.businessEmail; $('bAddr').value = b.businessAddress;
    $('bPBasic').value = b.priceBasic; $('bPPro').value = b.pricePro; $('bDue').value = b.dueDays; $('bPrefix').value = b.invoicePrefix;
    $('bPay').value = b.paymentInstructions; $('bSales').value = b.salesLink; $('bNotify').value = b.notifyEmail;
    $('bSmtpHost').value = b.smtpHost; $('bSmtpPort').value = b.smtpPort; $('bSmtpSec').value = b.smtpSecure; $('bSmtpUser').value = b.smtpUser;
    $('bSmtpPass').value = ''; $('bPassHelp').textContent = b.smtpPassSet ? 'A password is saved. Leave blank to keep it.' : 'Not saved yet.';
    $('bFrom').value = b.fromEmail; $('bFromName').value = b.fromName;
    if (!$('bTestTo').value) $('bTestTo').value = b.notifyEmail || b.businessEmail || '';
  }
  function gather() {
    return { signupsOpen: $('bOpen').checked, businessName: $('bName').value, businessEmail: $('bEmail').value, businessAddress: $('bAddr').value,
      priceBasic: $('bPBasic').value, pricePro: $('bPPro').value, dueDays: $('bDue').value, invoicePrefix: $('bPrefix').value,
      paymentInstructions: $('bPay').value, salesLink: $('bSales').value, notifyEmail: $('bNotify').value,
      smtpHost: $('bSmtpHost').value, smtpPort: $('bSmtpPort').value, smtpSecure: $('bSmtpSec').value, smtpUser: $('bSmtpUser').value,
      smtpPass: $('bSmtpPass').value, fromEmail: $('bFrom').value, fromName: $('bFromName').value };
  }
  async function save(quiet) {
    const r = await call('master_billing_save', { billing: gather() });
    if (r.ok) { D.billing = r.billing; D.mailReady = r.mailReady; fillSettings(); render(); if (!quiet) RQ.toast('Settings saved', 'ok'); }
    return r.ok;
  }
  async function test() {
    if (!(await save(true))) return;
    RQ.toast('Sending…');
    const r = await call('master_test_mail', { to: $('bTestTo').value });
    if (r.ok) RQ.toast('Test email sent — check the inbox (and spam folder)', 'ok');
    load();
  }
  async function paid(id) {
    const i = D.invoices.find(x => x.id === id);
    if (!confirm(`Mark ${i.number} (${i.amount}) as paid?\n\n${i.name}'s account switches to ${PL[i.plan]} for one year, and they get a receipt email.`)) return;
    const r = await call('master_invoice_paid', { id });
    if (r.ok) { RQ.toast(`Paid — ${r.account || 'account'} is ${PL[i.plan]} until ${day(r.until)}` + (r.emailed ? '' : ' (receipt not emailed)'), 'ok'); load(); }
  }
  async function resend(id) { const r = await call('master_invoice_resend', { id }); if (r.ok) { RQ.toast('Invoice emailed', 'ok'); load(); } }
  async function voidInv(id) {
    const i = D.invoices.find(x => x.id === id);
    if (!confirm(`Cancel invoice ${i.number}? The account stays on its current plan.`)) return;
    const r = await call('master_invoice_void', { id }); if (r.ok) { RQ.toast('Invoice cancelled', 'ok'); load(); }
  }
  function copyText(t, msg) { try { navigator.clipboard.writeText(t).then(() => RQ.toast(msg, 'ok'), () => prompt('Copy this link:', t)); } catch (e) { prompt('Copy this link:', t); } }
  function copyLink(id) { copyText(D.invoices.find(x => x.id === id).url, 'Invoice link copied'); }
  function copy() { copyText(D.signupUrl, 'Sign-up link copied'); }
  async function lead(id, status) { const r = await call('master_lead_update', { id, status }); if (r.ok) { D.leads.find(l => l.id === id).status = status; render(); } }

  async function openNew() {
    const r = await call('master_list'); if (!r.ok) return;
    accounts = r.admins.filter(a => !a.isMaster && !a.teamOf);
    if (!accounts.length) { RQ.toast('Create a host account first (Accounts page).', 'error'); return; }
    $('nAcct').innerHTML = accounts.map(a => `<option value="${a.id}">${RQ.esc(a.name)} — ${RQ.esc(a.username)} (${PL[a.plan] || a.plan})</option>`).join('');
    fillEmail(); $('newModal').classList.remove('hidden');
  }
  function fillEmail() { const a = accounts.find(x => x.id === +$('nAcct').value); $('nEmail').value = a ? (a.email || (/@/.test(a.username) ? a.username : '')) : ''; }
  function closeNew() { $('newModal').classList.add('hidden'); }
  async function createInv() {
    const r = await call('master_invoice_new', { adminId: $('nAcct').value, plan: $('nPlan').value, email: $('nEmail').value, send: $('nSend').checked ? '1' : '0' });
    if (!r.ok) return;
    closeNew(); tab('invoices'); $('invFilter').value = 'unpaid';
    RQ.toast(`Invoice ${r.invoice} created` + ($('nSend').checked ? (r.emailed ? ' and emailed' : ` — not emailed: ${r.error}`) : ''), r.emailed || !$('nSend').checked ? 'ok' : 'error');
    load();
  }
  document.addEventListener('DOMContentLoaded', () => { tab((location.hash || '#invoices').slice(1) || 'invoices'); load(); });
  return { tab, render, save, test, paid, resend, voidInv, copyLink, copy, lead, openNew, fillEmail, closeNew, createInv };
})();
</script>
</body>
</html>
