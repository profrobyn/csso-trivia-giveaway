<?php
/* Sign in: password, then the 6-digit code from an authenticator app (required for every admin). */
require_once __DIR__ . '/lib.php';
$setupError = null;
try {
    if (current_admin()) { header('Location: setup.php'); exit; }
} catch (Throwable $e) { $setupError = $e->getMessage(); }
$pageTitle = 'Sign in — ' . APP_NAME;
?><!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/partials/head.php'; ?></head>
<body>
<div class="blobs"><i></i><i></i><i></i></div>
<div class="min-h-[100dvh] grid lg:grid-cols-2">
  <!-- brand side -->
  <aside class="hidden lg:flex flex-col justify-between p-12">
    <span class="logo !text-2xl"><span class="logo-badge"><span>⚡</span></span><?= htmlspecialchars(APP_NAME) ?></span>
    <div class="space-y-6">
      <h1 class="font-display text-6xl font-bold leading-[1.02]">Live trivia that<br>gets the room <span class="grad-text">buzzing.</span></h1>
      <p class="text-muted text-lg max-w-md">Build your quiz, launch it, and everyone plays from their phones. Speed scores, surprise prizes, and a podium finish.</p>
      <div class="flex gap-3 text-4xl"><span class="bob">🎉</span><span class="bob" style="animation-delay:.3s">🏆</span><span class="bob" style="animation-delay:.6s">⚡</span><span class="bob" style="animation-delay:.9s">🎁</span></div>
    </div>
    <p class="text-dim text-sm text-[color:var(--dim)]">Two-factor sign-in keeps every host account safe.</p>
  </aside>

  <main class="flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="lg:hidden text-center mb-6"><span class="logo !text-2xl"><span class="logo-badge"><span>⚡</span></span><?= htmlspecialchars(APP_NAME) ?></span></div>
      <?php if ($setupError): ?>
        <div class="glass p-6 border-rose-400/60"><h2 class="font-display text-xl font-bold mb-2">Setup problem</h2><p class="text-rose-200 text-sm"><?= htmlspecialchars($setupError) ?></p></div>
      <?php else: ?>
      <div class="glass p-6 md:p-8 rise-in" id="card">

        <!-- create master (first run) -->
        <section data-step="master" class="hidden space-y-4">
          <div><span class="chip chip-gold"><i class="fa-solid fa-crown"></i> First-time setup</span>
            <h2 class="font-display text-3xl font-bold mt-3">Create the master admin</h2>
            <p class="text-muted text-sm mt-1">This account manages every other host account. It's linked to <b class="text-white" id="mPhoneShow"></b>.</p></div>
          <div><label class="lbl">Your name</label><input id="mName" class="field" placeholder="e.g. Robyn Brammer"></div>
          <div><label class="lbl">Username or email</label><input id="mUser" class="field" autocomplete="username"></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="lbl">Password</label><input id="mPw" type="password" class="field" autocomplete="new-password"></div>
            <div><label class="lbl">Repeat password</label><input id="mPw2" type="password" class="field" autocomplete="new-password"></div>
          </div>
          <p class="help -mt-2">At least 10 characters, with letters and numbers.</p>
          <div><label class="lbl">Confirm the master phone number</label><input id="mPhone" class="field" inputmode="tel" placeholder="(###) ###-####"></div>
          <button class="btn btn-primary btn-lg w-full" onclick="L.createMaster()">Create master account <i class="fa-solid fa-arrow-right"></i></button>
        </section>

        <!-- password -->
        <section data-step="password" class="hidden space-y-4">
          <div><h2 class="font-display text-3xl font-bold">Welcome back 👋</h2><p class="text-muted text-sm mt-1">Sign in to set up and run your game.</p></div>
          <div><label class="lbl">Username or email</label><input id="lUser" class="field" autocomplete="username" autofocus></div>
          <div><label class="lbl">Password</label><input id="lPw" type="password" class="field" autocomplete="current-password"></div>
          <button class="btn btn-primary btn-lg w-full" onclick="L.login()">Continue <i class="fa-solid fa-arrow-right"></i></button>
          <a href="signup.php" class="glass-2 !rounded-2xl p-3 flex items-center gap-3 hover:bg-white/10 transition">
            <span class="text-3xl">🎤</span><span><span class="block font-bold">New here? Create a free account</span><span class="block text-xs text-muted">Or see Basic, Pro and Enterprise plans</span></span>
            <i class="fa-solid fa-arrow-right ml-auto text-muted"></i></a>
        </section>

        <!-- 6-digit code -->
        <section data-step="totp" class="hidden space-y-4 text-center">
          <div class="text-6xl">🔐</div>
          <div><h2 class="font-display text-3xl font-bold">Two-step check</h2><p class="text-muted text-sm mt-1">Open your authenticator app and enter the 6-digit code for <?= htmlspecialchars(APP_NAME) ?>.</p></div>
          <input id="tCode" class="field code-input !text-3xl !py-4" inputmode="numeric" autocomplete="one-time-code" maxlength="9" placeholder="••••••">
          <button class="btn btn-primary btn-lg w-full" onclick="L.verify()">Verify <i class="fa-solid fa-shield-halved"></i></button>
          <p class="help">Lost your phone? Type one of your backup codes (like <code>a1b2-c3d4</code>) instead.</p>
          <button class="btn btn-ghost btn-sm" onclick="L.cancel()">← Use a different account</button>
        </section>

        <!-- enroll authenticator -->
        <section data-step="enroll" class="hidden space-y-4">
          <div><span class="chip chip-pink"><i class="fa-solid fa-shield-halved"></i> Required</span>
            <h2 class="font-display text-3xl font-bold mt-3">Set up two-step sign-in</h2>
            <p class="text-muted text-sm mt-1">Every admin uses an authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password…).</p></div>
          <p id="ePhone" class="hidden chip chip-gold"></p>
          <ol class="space-y-3 text-sm">
            <li class="flex gap-3"><span class="chip chip-cyan !px-2.5">1</span><span>Open the app and tap <b>+</b> / <b>Add account</b>, then scan this QR code:</span></li>
          </ol>
          <div class="flex justify-center"><div class="bg-white p-3 rounded-2xl"><div id="eQr"></div></div></div>
          <details class="glass-2 p-3 text-sm"><summary class="cursor-pointer font-semibold">Can't scan? Enter this key instead</summary>
            <p id="eSecret" class="font-mono font-bold text-amber-200 mt-2 break-all select-all"></p></details>
          <ol class="space-y-3 text-sm" start="2">
            <li class="flex gap-3"><span class="chip chip-cyan !px-2.5">2</span><span>Type the 6-digit code the app shows:</span></li>
          </ol>
          <input id="eCode" class="field code-input !text-3xl !py-4" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••">
          <button class="btn btn-primary btn-lg w-full" onclick="L.enrollConfirm()">Turn on two-step sign-in</button>
          <button class="btn btn-ghost btn-sm w-full" onclick="L.cancel()">Cancel</button>
        </section>

        <!-- backup codes -->
        <section data-step="backup" class="hidden space-y-4">
          <div class="text-center"><div class="text-6xl">🗝️</div><h2 class="font-display text-3xl font-bold mt-2">Save your backup codes</h2>
            <p class="text-muted text-sm mt-1">If you lose your phone, each code lets you sign in <b>once</b>. Store them somewhere safe — they won't be shown again.</p></div>
          <div id="bCodes" class="grid grid-cols-2 gap-2 font-mono text-lg font-bold text-center"></div>
          <button class="btn w-full" onclick="L.copyCodes()"><i class="fa-solid fa-copy"></i> Copy codes</button>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="bSaved" class="w-4 h-4 accent-pink-500"> I've saved these codes</label>
          <button class="btn btn-primary btn-lg w-full" onclick="L.afterBackup()">Continue <i class="fa-solid fa-arrow-right"></i></button>
        </section>

        <!-- new password -->
        <section data-step="change_pw" class="hidden space-y-4">
          <div><h2 class="font-display text-3xl font-bold">Choose your password</h2><p class="text-muted text-sm mt-1">You signed in with a temporary password. Please pick your own.</p></div>
          <div><label class="lbl">New password</label><input id="nPw" type="password" class="field" autocomplete="new-password"></div>
          <div><label class="lbl">Repeat new password</label><input id="nPw2" type="password" class="field" autocomplete="new-password"></div>
          <p class="help -mt-2">At least 10 characters, with letters and numbers.</p>
          <button class="btn btn-primary btn-lg w-full" onclick="L.newPassword()">Save and continue</button>
        </section>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<script>
window.L = (function () {
  const $ = id => document.getElementById(id);
  let nextAfterBackup = 'done', codes = [];
  function step(name) {
    document.querySelectorAll('[data-step]').forEach(s => s.classList.toggle('hidden', s.dataset.step !== name));
    const first = document.querySelector(`[data-step="${name}"] input`); if (first) setTimeout(() => first.focus(), 50);
    if (name === 'enroll') enrollStart();
  }
  function go(next) { if (next === 'done') location.href = 'setup.php'; else step(next); }
  async function status() {
    const r = await RQ.api('auth_status');
    if (!r.ok) { RQ.toast(r.error, 'error'); return; }
    window.RQ_CSRF = r.csrf;
    $('mPhoneShow').textContent = r.masterPhone;
    if (r.signedIn) return go('done');
    if (r.needMaster) return step('master');
    step(['totp', 'enroll', 'change_pw'].includes(r.stage) ? r.stage : 'password');
  }
  async function call(action, data) {
    const r = await RQ.api(action, data);
    if (!r.ok) { RQ.toast(r.error, 'error'); if (r.code === 'RESTART') step('password'); }
    return r;
  }
  async function createMaster() {
    const r = await call('create_master', { display_name: $('mName').value, username: $('mUser').value, password: $('mPw').value, password2: $('mPw2').value, phone_confirm: $('mPhone').value });
    if (r.ok) step(r.stage);
  }
  async function login() {
    const r = await call('login', { username: $('lUser').value, password: $('lPw').value });
    if (r.ok) step(r.stage);
  }
  async function verify() {
    const r = await call('verify_2fa', { code: $('tCode').value });
    if (r.ok) go(r.next); else $('tCode').select();
  }
  async function enrollStart() {
    const r = await call('enroll_start');
    if (!r.ok) return;
    $('eQr').innerHTML = '';
    new QRCode($('eQr'), { text: r.uri, width: 200, height: 200 });
    $('eSecret').textContent = r.secret;
    $('ePhone').classList.toggle('hidden', !r.phone);
    if (r.phone) $('ePhone').innerHTML = `<i class="fa-solid fa-crown"></i> Master account · linked to ${r.phone}`;
  }
  async function enrollConfirm() {
    const r = await call('enroll_confirm', { code: $('eCode').value });
    if (!r.ok) return;
    codes = r.backupCodes; nextAfterBackup = r.next;
    $('bCodes').innerHTML = codes.map(c => `<span class="glass-2 py-2">${c}</span>`).join('');
    step('backup');
  }
  function copyCodes() { navigator.clipboard.writeText(codes.join('\n')); RQ.toast('Backup codes copied', 'ok'); }
  function afterBackup() { if (!$('bSaved').checked) { RQ.toast('Please save your backup codes first, then tick the box.', 'error'); return; } go(nextAfterBackup); }
  async function newPassword() {
    const r = await call('set_new_password', { password: $('nPw').value, password2: $('nPw2').value });
    if (r.ok) go(r.next);
  }
  async function cancel() { await RQ.api('cancel_login'); step('password'); }
  document.addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    const vis = document.querySelector('[data-step]:not(.hidden)'); if (!vis) return;
    const btn = vis.querySelector('.btn-primary'); if (btn) btn.click();
  });
  document.addEventListener('DOMContentLoaded', status);
  return { createMaster, login, verify, enrollConfirm, copyCodes, afterBackup, newPassword, cancel };
})();
</script>
</body>
</html>
