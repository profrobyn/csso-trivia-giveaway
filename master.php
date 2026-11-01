<?php
/* Master admin: create, pause, extend and remove host (admin) accounts. */
require_once __DIR__ . '/lib.php';
$me = current_admin();
if (!$me) { header('Location: login.php'); exit; }
if (!is_master($me)) { header('Location: setup.php'); exit; }
$navActive = 'master';
$pageTitle = 'Accounts — ' . APP_NAME;
?><!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/partials/head.php'; ?></head>
<body>
<div class="blobs"><i></i><i></i><i></i></div>
<?php include __DIR__ . '/partials/admin_top.php'; ?>

<main class="p-3 md:p-6 max-w-[1500px] mx-auto space-y-5 pb-16">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <p class="chip chip-gold"><i class="fa-solid fa-crown"></i> Master admin · linked to <?= htmlspecialchars(format_phone(MASTER_PHONE)) ?></p>
      <h1 class="font-display text-4xl font-bold mt-2">Host accounts</h1>
      <p class="text-muted">Each host gets their own questions, events, players and QR code. Every account must use two-step sign-in.</p>
    </div>
    <button class="btn btn-primary btn-lg" onclick="M.openNew()"><i class="fa-solid fa-user-plus"></i> New host account</button>
  </div>

  <div id="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3"></div>

  <div class="card space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="font-display text-xl font-bold">All accounts</h3>
      <input id="search" class="field !w-56 !py-2" placeholder="Search name, org, username…" oninput="M.render()">
    </div>
    <div id="list" class="grid lg:grid-cols-2 2xl:grid-cols-3 gap-3"></div>
  </div>
</main>

<!-- create / edit modal -->
<div id="modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(5,4,20,.75);backdrop-filter:blur(6px)">
  <div class="glass w-full max-w-xl p-6 space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar" style="background:rgba(20,15,55,.97)">
    <div class="flex items-center justify-between"><h3 id="mTitle" class="font-display text-2xl font-bold"></h3>
      <button class="btn btn-ghost btn-sm" onclick="M.close()"><i class="fa-solid fa-xmark text-lg"></i></button></div>
    <input type="hidden" id="fId">
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="lbl">Name</label><input id="fName" class="field" placeholder="Dean Smith"></div>
      <div><label class="lbl">Organization</label><input id="fOrg" class="field" placeholder="Rio Hondo College"></div>
      <div class="newOnly"><label class="lbl">Username or email</label><input id="fUser" class="field" autocomplete="off" oninput="M.suggestHandle()"></div>
      <div class="sm:col-span-2"><label class="lbl">Web name — their own folder and QR link</label>
        <div class="flex items-center gap-1 field !p-0 !pl-3 overflow-hidden"><span id="baseShow" class="text-muted text-sm whitespace-nowrap"></span>
          <input id="fHandle" class="flex-1 bg-transparent py-2.5 pr-3 focus:outline-none font-bold text-cyan-200" placeholder="dean" maxlength="30"></div>
        <p class="help">Letters, numbers and dashes. A folder with this name is created automatically.</p></div>
      <div><label class="lbl">Phone (optional)</label><input id="fPhone" class="field" inputmode="tel"></div>
      <div class="newOnly"><label class="lbl">Temporary password</label>
        <div class="flex gap-2"><input id="fPw" class="field font-mono" autocomplete="off"><button class="btn btn-sm" onclick="M.genPw()" title="Generate"><i class="fa-solid fa-wand-magic-sparkles"></i></button></div>
        <p class="help">They'll set their own password and authenticator on first sign-in.</p></div>
      <div><label class="lbl">Plan</label><select id="fPlan" class="field">
        <option value="free">Free — 10 players, 2 quizzes/month</option>
        <option value="basic" selected>Basic — 50 players, 3 quizzes/month</option>
        <option value="pro">Pro — 1,000 players, 10 quizzes/month, branding, PowerPoint, Excel</option>
        <option value="enterprise">Enterprise — unlimited + organization users</option></select></div>
      <div><label class="lbl">License ends (optional)</label><input id="fExp" type="date" class="field"></div>
      <div class="editOnly"><label class="lbl">Status</label><select id="fStatus" class="field"><option value="active">Active</option><option value="paused">Paused (can't sign in, game offline)</option></select></div>
    </div>
    <div><label class="lbl">Notes (only you see these — e.g. invoice #, price)</label><textarea id="fNotes" rows="2" class="field"></textarea></div>
    <div class="flex justify-end gap-2"><button class="btn" onclick="M.close()">Cancel</button><button class="btn btn-primary" onclick="M.save()"><i class="fa-solid fa-floppy-disk"></i> Save</button></div>
  </div>
</div>

<script>
window.M = (function () {
  const $ = id => document.getElementById(id);
  let admins = [];
  async function call(a, d = {}) { const r = await RQ.api(a, d); if (!r.ok) { RQ.toast(r.error, 'error'); if (r.code === 'AUTH') setTimeout(() => location.href = 'login.php', 1000); } return r; }
  async function load() { const r = await call('master_list'); if (r.ok) { admins = r.admins; render(); } }
  const d = ms => ms ? new Date(ms).toLocaleDateString() : '—';
  function statusChip(a) {
    if (a.isMaster) return '<span class="chip chip-gold">👑 Master</span>';
    if (a.teamOf) return `<span class="chip chip-cyan">🏛️ ${RQ.esc(a.teamOfName || '')} user</span>`;
    if (a.status === 'active' && a.source === 'signup' && Date.now() - a.createdAt < 7 * 864e5) return '<span class="chip chip-pink">✨ New sign-up</span>';
    if (a.status === 'paused') return '<span class="chip chip-red">⏸ Paused</span>';
    if (a.expired) return '<span class="chip chip-red">⌛ Expired</span>';
    return '<span class="chip chip-green">● Active</span>';
  }
  function render() {
    const hosts = admins.filter(a => !a.isMaster);
    const active = hosts.filter(a => a.status === 'active' && !a.expired).length;
    $('stats').innerHTML = [
      ['👥', hosts.length, 'host accounts'], ['✅', active, 'active licenses'],
      ['⏳', hosts.filter(a => a.expiresAt && !a.expired && a.expiresAt - Date.now() < 30 * 864e5).length, 'expiring in 30 days'],
      ['🎮', admins.reduce((s, a) => s + a.players, 0), 'players (all accounts)'],
    ].map(([e, n, l]) => `<div class="card flex items-center gap-3"><span class="text-3xl">${e}</span><div><p class="font-display text-3xl font-bold leading-none">${n}</p><p class="text-muted text-sm">${l}</p></div></div>`).join('');
    const s = $('search').value.toLowerCase();
    const list = admins.filter(a => !s || [a.name, a.org, a.username].join(' ').toLowerCase().includes(s));
    $('list').innerHTML = list.map(a => `
      <div class="glass-2 p-4 space-y-3 ${a.isMaster ? 'ring-glow' : ''}">
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-center gap-3 min-w-0">
            <span class="w-11 h-11 rounded-2xl grid place-items-center font-bold text-lg shrink-0" style="background:linear-gradient(135deg,var(--pink),var(--violet))">${RQ.esc((a.name || a.username).slice(0, 1).toUpperCase())}</span>
            <div class="min-w-0"><p class="font-display text-lg font-bold truncate">${RQ.esc(a.name)}</p><p class="text-xs text-muted truncate">${RQ.esc(a.username)}${a.org ? ' · ' + RQ.esc(a.org) : ''}</p></div>
          </div>${statusChip(a)}
        </div>
        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
          <span class="text-muted">Player link</span><a class="font-bold text-cyan-200 truncate hover:underline" href="${RQ.esc(a.joinPath)}" target="_blank">…/${RQ.esc(a.joinPath)}</a>
          <span class="text-muted">Plan</span><span class="font-bold">${a.teamOf ? 'Enterprise (organization user)' : ({ free: 'Free', basic: 'Basic', pro: 'Pro ✨', enterprise: 'Enterprise 🏛️' }[a.plan] || a.plan)}${a.teamSize ? ` · ${a.teamSize} users` : ''}</span>
          ${a.planUntil ? `<span class="text-muted">Plan paid through</span><span class="font-semibold ${a.planExpired ? 'text-rose-300' : ''}">${d(a.planUntil)}${a.planExpired ? ' (ended → Free)' : ''}</span>` : ''}
          ${a.openInvoices ? `<span class="text-muted">Invoices</span><a href="billing.php#invoices" class="font-bold text-amber-200 hover:underline">🧾 ${a.openInvoices} waiting for payment</a>` : ''}
          ${a.email && a.email !== a.username ? `<span class="text-muted">Email</span><span class="font-semibold truncate">${RQ.esc(a.email)}</span>` : ''}
          <span class="text-muted">Game code</span><span class="font-bold tracking-widest">${a.gameCode}</span>
          <span class="text-muted">License ends</span><span class="font-semibold ${a.expired ? 'text-rose-300' : ''}">${a.isMaster ? 'Never' : a.expiresAt ? d(a.expiresAt) : 'No end date'}</span>
          <span class="text-muted">Two-step sign-in</span><span class="font-semibold">${a.twoFactor ? '✅ Set up' : '⏳ On first sign-in'}</span>
          <span class="text-muted">Last sign-in</span><span class="font-semibold">${d(a.lastLogin)}</span>
          <span class="text-muted">Usage</span><span class="font-semibold">${a.events} events · ${a.banks} banks · ${a.players} players</span>
          ${a.phone ? `<span class="text-muted">Phone</span><span class="font-semibold">${RQ.esc(a.phone)}</span>` : ''}
        </div>
        ${a.notes ? `<p class="text-xs text-muted italic border-l-2 border-white/20 pl-2">${RQ.esc(a.notes)}</p>` : ''}
        ${a.isMaster ? '' : `<div class="flex flex-wrap gap-1.5">
          <button class="btn btn-sm" onclick="M.edit(${a.id})"><i class="fa-solid fa-pen"></i> Edit</button>
          <button class="btn btn-sm" onclick="M.toggle(${a.id})">${a.status === 'paused' ? '<i class="fa-solid fa-play"></i> Resume' : '<i class="fa-solid fa-pause"></i> Pause'}</button>
          <button class="btn btn-sm" onclick="M.resetPw(${a.id})"><i class="fa-solid fa-key"></i> Password</button>
          <button class="btn btn-sm" onclick="M.reset2fa(${a.id})"><i class="fa-solid fa-shield-halved"></i> Reset 2-step</button>
          <button class="btn btn-sm" onclick="M.newCode(${a.id})" title="Give them a new game code / QR"><i class="fa-solid fa-qrcode"></i></button>
          <button class="btn btn-sm btn-danger" onclick="M.del(${a.id})"><i class="fa-solid fa-trash"></i></button></div>`}
      </div>`).join('') || '<p class="text-muted">No accounts match.</p>';
  }
  function suggestHandle() {
    $('fHandle').value = $('fUser').value.toLowerCase().replace(/@.*$/, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30);
  }
  function genPw() {
    const c = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'; let p = '';
    const arr = crypto.getRandomValues(new Uint32Array(12)); arr.forEach(n => p += c[n % c.length]);
    $('fPw').value = p.slice(0, 6) + (arr[0] % 10) + p.slice(6);
  }
  function fill(a) {
    $('baseShow').textContent = location.host + location.pathname.replace(/[^/]*$/, '');
    $('fHandle').value = a ? a.handle : '';
    $('fPlan').value = a && a.plan ? a.plan : 'basic';
    $('fPlan').closest('div').classList.toggle('hidden', !!(a && a.teamOf));
    $('fId').value = a ? a.id : ''; $('fName').value = a ? a.name : ''; $('fOrg').value = a ? a.org : ''; $('fUser').value = '';
    $('fPhone').value = a ? a.phone : ''; $('fNotes').value = a ? a.notes : ''; $('fStatus').value = a ? a.status : 'active';
    $('fExp').value = a && a.expiresAt ? new Date(a.expiresAt).toISOString().slice(0, 10) : '';
    document.querySelectorAll('.newOnly').forEach(e => e.classList.toggle('hidden', !!a));
    document.querySelectorAll('.editOnly').forEach(e => e.classList.toggle('hidden', !a));
    $('mTitle').textContent = a ? 'Edit ' + a.name : 'New host account';
    $('modal').classList.remove('hidden');
  }
  function openNew() { fill(null); genPw(); }
  function edit(id) { fill(admins.find(a => a.id === id)); }
  function close() { $('modal').classList.add('hidden'); }
  async function save() {
    const id = $('fId').value;
    const payload = { plan: $('fPlan').value, handle: $('fHandle').value, display_name: $('fName').value, org: $('fOrg').value, phone: $('fPhone').value, notes: $('fNotes').value, expires: $('fExp').value };
    let r;
    if (id) r = await call('master_update', { ...payload, id, status: $('fStatus').value });
    else r = await call('master_create', { ...payload, username: $('fUser').value, password: $('fPw').value });
    if (!r.ok) return;
    close(); load();
    if (!id) {
      const msg = `Your ${document.title.split('—').pop().trim()} host account is ready.\nSign in: ${location.origin}${location.pathname.replace(/[^/]*$/, '')}login.php\nYour player link (for your QR code): ${location.origin}${location.pathname.replace(/[^/]*$/, '')}${r.admin.joinPath}\nUsername: ${r.admin.username}\nTemporary password: ${payload.password || $('fPw').value}\nYou'll set your own password and an authenticator app the first time you sign in.`;
      try { navigator.clipboard.writeText(msg).catch(() => {}); } catch (e) {}
      alert('Account created! The sign-in details were copied to your clipboard so you can send them:\n\n' + msg);
    } else RQ.toast('Saved', 'ok');
  }
  async function toggle(id) {
    const a = admins.find(x => x.id === id);
    const r = await call('master_update', { id, status: a.status === 'paused' ? 'active' : 'paused', expires: a.expiresAt ? new Date(a.expiresAt).toISOString().slice(0, 10) : '' });
    if (r.ok) { RQ.toast(a.status === 'paused' ? 'Account resumed' : 'Account paused', 'ok'); load(); }
  }
  async function resetPw(id) {
    const a = admins.find(x => x.id === id);
    const pw = prompt(`New temporary password for ${a.name} (10+ characters, letters and numbers). They'll be asked to change it.`);
    if (!pw) return;
    const r = await call('master_reset_password', { id, password: pw });
    if (r.ok) { RQ.toast('Temporary password set', 'ok'); load(); }
  }
  async function reset2fa(id) {
    const a = admins.find(x => x.id === id);
    if (!confirm(`Reset two-step sign-in for ${a.name}? They'll set up a new authenticator the next time they sign in.`)) return;
    const r = await call('master_reset_2fa', { id }); if (r.ok) { RQ.toast('Two-step sign-in reset', 'ok'); load(); }
  }
  async function newCode(id) {
    const a = admins.find(x => x.id === id);
    if (!confirm(`Give ${a.name} a new game code? Their old QR code and link will stop working.`)) return;
    const r = await call('master_new_code', { id }); if (r.ok) { RQ.toast('New game code: ' + r.admin.gameCode, 'ok'); load(); }
  }
  async function del(id) {
    const a = admins.find(x => x.id === id);
    if (prompt(`This permanently deletes ${a.name}'s account, questions, events, players and scores.\n\nType DELETE to confirm.`) !== 'DELETE') return;
    const r = await call('master_delete', { id }); if (r.ok) { RQ.toast('Account deleted', 'ok'); load(); }
  }
  document.addEventListener('DOMContentLoaded', () => { load(); $('modal').addEventListener('click', e => { if (e.target.id === 'modal') close(); }); });
  return { suggestHandle, render, openNew, edit, close, save, genPw, toggle, resetPw, reset2fa, newCode, del };
})();
</script>
</body>
</html>
