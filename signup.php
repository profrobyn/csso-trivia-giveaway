<?php
/* Public sign-up: Free (instant), Basic / Pro (account now + emailed invoice), Enterprise (book a sales meeting).
   A signed-in host who opens this page gets "upgrade" buttons for their own account instead. */
require_once __DIR__ . '/lib.php';
$me = null; $setupError = null;
try { $me = current_admin(); } catch (Throwable $e) { $setupError = $e->getMessage(); }
$canUpgrade = $me && !is_master($me) && empty($me['team_of']);
$pageTitle = 'Plans & sign-up — ' . APP_NAME;
?><!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/partials/head.php'; ?></head>
<body>
<div class="blobs"><i></i><i></i><i></i></div>
<?php if ($me): ?><script>window.RQ_CSRF = <?= json_encode($_SESSION['csrf']) ?>;</script><?php endif; ?>

<header class="px-4 md:px-8 py-4 flex items-center justify-between gap-3">
  <a href="signup.php" class="logo !text-2xl"><span class="logo-badge"><span>⚡</span></span><?= htmlspecialchars(APP_NAME) ?></a>
  <?php if ($me): ?>
    <a href="setup.php" class="btn btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to my quizzes</a>
  <?php else: ?>
    <a href="login.php" class="btn btn-sm"><i class="fa-solid fa-right-to-bracket"></i> Sign in</a>
  <?php endif; ?>
</header>

<main class="px-4 md:px-8 pb-20 max-w-[1300px] mx-auto space-y-10">
  <?php if ($setupError): ?>
    <div class="glass p-6"><h2 class="font-display text-xl font-bold mb-2">Setup problem</h2><p class="text-rose-200 text-sm"><?= htmlspecialchars($setupError) ?></p></div>
  <?php endif; ?>

  <section class="text-center space-y-3 pt-4">
    <h1 class="font-display text-4xl md:text-6xl font-bold leading-[1.05]" id="heroTitle">
      <?php if ($canUpgrade): ?>Upgrade your <span class="grad-text">RizQuiz</span><?php else: ?>Host your own <span class="grad-text">live quiz</span><?php endif; ?></h1>
    <p class="text-muted text-lg max-w-2xl mx-auto" id="heroSub">
      <?php if ($canUpgrade): ?>Pick a plan. We'll email you an invoice, and your account upgrades as soon as payment arrives.
      <?php else: ?>Build a quiz, show the QR code, and everyone plays from their phones. Speed scores, surprise prizes and a podium finish. Start free in a minute.<?php endif; ?></p>
    <div class="flex justify-center gap-3 text-4xl"><span class="bob">🎉</span><span class="bob" style="animation-delay:.3s">🏆</span><span class="bob" style="animation-delay:.6s">⚡</span><span class="bob" style="animation-delay:.9s">🎁</span></div>
  </section>

  <div id="closedNote" class="hidden glass p-6 text-center max-w-xl mx-auto">
    <p class="font-display text-2xl font-bold">New sign-ups are paused</p>
    <p class="text-muted mt-1">Please check back soon, or book a meeting below.</p>
  </div>

  <!-- plan cards -->
  <section id="plans" class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 items-stretch"></section>

  <!-- account form (Free / Basic / Pro) -->
  <section id="formBox" class="hidden glass p-5 md:p-8 max-w-3xl mx-auto space-y-5 scroll-mt-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="chip" id="fPlanChip"></p>
        <h2 class="font-display text-3xl font-bold mt-2">Create your account</h2>
      </div>
      <button class="btn btn-ghost btn-sm" onclick="SU.closeForms()"><i class="fa-solid fa-xmark"></i> Close</button>
    </div>
    <div id="fPaidNote" class="hidden rounded-2xl p-4 text-sm" style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.45)"></div>
    <form id="suForm" class="grid sm:grid-cols-2 gap-4" onsubmit="event.preventDefault(); SU.submit()">
      <div><label class="lbl" for="sName">Your name</label><input id="sName" class="field" autocomplete="name" required maxlength="80"></div>
      <div><label class="lbl" for="sOrg">School / organization <span class="text-muted font-normal">(optional)</span></label><input id="sOrg" class="field" autocomplete="organization" maxlength="120"></div>
      <div class="sm:col-span-2"><label class="lbl" for="sEmail">Email — this is your username</label><input id="sEmail" type="email" class="field" autocomplete="email" required oninput="SU.suggest()"></div>
      <div class="sm:col-span-2"><label class="lbl" for="sHandle">Your players' link</label>
        <div class="flex items-center gap-1 field !p-0 !pl-3 overflow-hidden"><span id="sBase" class="text-muted text-sm whitespace-nowrap truncate max-w-[55%]"></span>
          <input id="sHandle" class="flex-1 min-w-0 bg-transparent py-2.5 pr-3 focus:outline-none font-bold text-cyan-200" maxlength="30" placeholder="your-name" oninput="this.dataset.touched=1"></div>
        <p class="help">Your QR code points here. Letters, numbers and dashes.</p></div>
      <div><label class="lbl" for="sPw">Password</label><input id="sPw" type="password" class="field" autocomplete="new-password" required minlength="10"><p class="help">At least 10 characters, with letters and numbers.</p></div>
      <div><label class="lbl" for="sPw2">Password again</label><input id="sPw2" type="password" class="field" autocomplete="new-password" required minlength="10"></div>
      <div class="hidden" aria-hidden="true"><label>Website <input id="sWebsite" tabindex="-1" autocomplete="off"></label></div>
      <label class="sm:col-span-2 flex items-start gap-3 cursor-pointer text-sm"><input id="sAgree" type="checkbox" class="mt-1 w-5 h-5 accent-violet-500">
        <span>I'll use RizQuiz responsibly and keep my sign-in safe. The first time I sign in I'll set up an authenticator app (Google or Microsoft Authenticator).</span></label>
      <div class="sm:col-span-2 flex justify-end">
        <button id="sGo" class="btn btn-primary btn-lg"><i class="fa-solid fa-rocket"></i> <span id="sGoLabel">Create my free account</span></button>
      </div>
    </form>
  </section>

  <!-- upgrade form (signed-in host) -->
  <section id="upBox" class="hidden glass p-5 md:p-8 max-w-2xl mx-auto space-y-4 scroll-mt-6">
    <div class="flex items-start justify-between gap-3">
      <div><p class="chip chip-gold" id="uPlanChip"></p><h2 class="font-display text-3xl font-bold mt-2">Upgrade my account</h2></div>
      <button class="btn btn-ghost btn-sm" onclick="SU.closeForms()"><i class="fa-solid fa-xmark"></i> Close</button>
    </div>
    <p class="text-muted" id="uText"></p>
    <div><label class="lbl" for="uEmail">Send the invoice to</label><input id="uEmail" type="email" class="field" value="<?= htmlspecialchars($me['email'] ?? (filter_var($me['username'] ?? '', FILTER_VALIDATE_EMAIL) ? $me['username'] : '')) ?>"></div>
    <div class="flex justify-end"><button id="uGo" class="btn btn-primary btn-lg" onclick="SU.upgrade()"><i class="fa-solid fa-file-invoice-dollar"></i> Email me the invoice</button></div>
  </section>

  <!-- enterprise -->
  <section id="salesBox" class="hidden glass p-5 md:p-8 max-w-3xl mx-auto space-y-5 scroll-mt-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div><p class="chip chip-cyan">🏛️ Enterprise</p><h2 class="font-display text-3xl font-bold mt-2">Set up a sales meeting</h2>
        <p class="text-muted">Unlimited quizzes and players, every Pro feature, and accounts for everyone at your institution with your official branding.</p></div>
      <button class="btn btn-ghost btn-sm" onclick="SU.closeForms()"><i class="fa-solid fa-xmark"></i> Close</button>
    </div>
    <div id="bookBox" class="hidden rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3" style="background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.45)">
      <p class="font-semibold">📅 Pick a time on our calendar right now</p>
      <a id="bookLink" href="#" target="_blank" rel="noopener" class="btn btn-primary"><i class="fa-solid fa-calendar-check"></i> Book a meeting</a>
    </div>
    <p class="text-sm text-muted" id="orForm">Or tell us about your team and when you're free, and we'll set it up:</p>
    <form class="grid sm:grid-cols-2 gap-4" onsubmit="event.preventDefault(); SU.sales()">
      <div><label class="lbl" for="eName">Your name</label><input id="eName" class="field" required autocomplete="name"></div>
      <div><label class="lbl" for="eOrg">Institution / company</label><input id="eOrg" class="field" required autocomplete="organization"></div>
      <div><label class="lbl" for="eEmail">Email</label><input id="eEmail" type="email" class="field" required autocomplete="email"></div>
      <div><label class="lbl" for="ePhone">Phone <span class="text-muted font-normal">(optional)</span></label><input id="ePhone" class="field" inputmode="tel" autocomplete="tel"></div>
      <div><label class="lbl" for="eSize">How many hosts?</label><select id="eSize" class="field"><option>2–5</option><option>6–20</option><option>21–50</option><option>50+</option><option>Not sure yet</option></select></div>
      <div><label class="lbl" for="eTimes">Good days / times to meet</label><input id="eTimes" class="field" placeholder="e.g. Tue or Thu afternoons"></div>
      <div class="sm:col-span-2"><label class="lbl" for="eMsg">Anything else? <span class="text-muted font-normal">(optional)</span></label><textarea id="eMsg" rows="3" class="field" placeholder="Events you run, number of players, questions…"></textarea></div>
      <div class="hidden" aria-hidden="true"><input id="eWebsite" tabindex="-1" autocomplete="off"></div>
      <div class="sm:col-span-2 flex justify-end"><button id="eGo" class="btn btn-primary btn-lg"><i class="fa-solid fa-paper-plane"></i> Request a meeting</button></div>
    </form>
  </section>

  <!-- result -->
  <section id="doneBox" class="hidden glass p-6 md:p-10 max-w-2xl mx-auto text-center space-y-4 scroll-mt-6"></section>
</main>

<script>
window.SU = (function () {
  const $ = id => document.getElementById(id);
  const SIGNED_IN = <?= json_encode((bool) $me) ?>, CAN_UPGRADE = <?= json_encode($canUpgrade) ?>;
  let info = null, chosen = 'free';
  const FEAT = p => [
    p.players ? `Up to <b>${RQ.fmt(p.players)}</b> players per quiz` : '<b>Unlimited</b> players',
    p.quizzes ? `<b>${p.quizzes}</b> quizzes a month` : '<b>Unlimited</b> quizzes',
    'Every question type: polls, word clouds, idea board…',
    'Prizes, leaderboard and podium',
    [p.branding, 'Your logo and colors'], [p.ppt, 'PowerPoint export'], [p.export, 'Excel results downloads'], [p.team, 'Accounts for your whole team'],
  ].map(f => Array.isArray(f) ? `<li class="${f[0] ? '' : 'opacity-40 line-through'}">${f[0] ? '✅' : '—'} ${f[1]}</li>` : `<li>✅ ${f}</li>`).join('');
  const LOOK = {
    free: { emoji: '🎈', blurb: 'Try it out with a small group.', btn: 'Start free', cls: 'btn' },
    basic: { emoji: '🎯', blurb: 'For classes, clubs and team meetings.', btn: 'Choose Basic', cls: 'btn btn-primary' },
    pro: { emoji: '✨', blurb: 'Big events with your own branding.', btn: 'Choose Pro', cls: 'btn btn-gold', tag: 'Most popular' },
    enterprise: { emoji: '🏛️', blurb: 'For your whole institution.', btn: 'Book a meeting', cls: 'btn btn-purple' },
  };

  async function load() {
    $('sBase').textContent = location.host + location.pathname.replace(/[^/]*$/, '');
    const r = await RQ.api('signup_info');
    if (!r.ok) { RQ.toast(r.error, 'error'); return; }
    info = r;
    if (!r.open && !CAN_UPGRADE) $('closedNote').classList.remove('hidden');
    if (r.salesLink) { $('bookBox').classList.remove('hidden'); $('bookLink').href = r.salesLink; }
    else $('orForm').textContent = 'Tell us about your team and when you\'re free, and we\'ll set up a meeting:';
    render();
    const want = new URLSearchParams(location.search).get('plan');
    if (want && r.plans.some(p => p.key === want)) choose(want);
  }

  function render() {
    const mine = info.me;
    $('plans').innerHTML = info.plans.map(p => {
      const L = LOOK[p.key];
      const current = mine && mine.plan === p.key;
      let action;
      if (p.key === 'enterprise') action = `<button class="${L.cls} w-full !justify-center" onclick="SU.choose('enterprise')">${L.btn}</button>`;
      else if (SIGNED_IN && !CAN_UPGRADE) action = `<p class="text-sm text-muted text-center">Your plan is managed by your organization.</p>`;
      else if (CAN_UPGRADE && p.key === 'free') action = current ? `<p class="chip chip-green mx-auto">Your current plan</p>` : `<p class="text-sm text-muted text-center">Included with every account</p>`;
      else if (!info.open && !CAN_UPGRADE) action = `<button class="btn w-full !justify-center" disabled>Sign-ups paused</button>`;
      else action = `<button class="${L.cls} w-full !justify-center" onclick="SU.choose('${p.key}')">${CAN_UPGRADE ? (current ? 'Renew for another year' : 'Upgrade to ' + p.label) : L.btn}</button>`;
      const price = p.key === 'enterprise' ? `<p class="font-display text-3xl font-bold">Let's talk</p><p class="text-muted text-sm">Priced for your institution</p>`
        : p.price ? `<p class="font-display text-4xl font-bold">$${p.price}<span class="text-lg text-muted font-semibold"> / year</span></p><p class="text-muted text-sm">Invoice by email</p>`
        : `<p class="font-display text-4xl font-bold">$0</p><p class="text-muted text-sm">Free forever</p>`;
      return `<div class="glass p-5 flex flex-col gap-4 relative ${p.key === 'pro' ? 'ring-glow' : ''} ${current ? 'outline outline-2 outline-emerald-400/70' : ''}">
        ${L.tag ? `<span class="chip chip-gold absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap">${L.tag}</span>` : ''}
        <div><p class="text-4xl">${L.emoji}</p><h3 class="font-display text-2xl font-bold mt-1">${p.label}</h3><p class="text-muted text-sm">${L.blurb}</p></div>
        ${price}
        <ul class="space-y-1.5 text-sm flex-1">${FEAT(p)}</ul>
        ${action}</div>`;
    }).join('');
    if (mine) {
      const pend = (mine.invoices || []).map(i => `<a class="underline font-bold" target="_blank" href="${RQ.esc(i.url)}">${RQ.esc(i.number)}</a>`).join(', ');
      $('heroSub').innerHTML = `You're on the <b>${RQ.esc(info.plans.find(p => p.key === mine.plan).label)}</b> plan${mine.planUntil ? ` (paid through ${new Date(mine.planUntil).toLocaleDateString()})` : ''}.`
        + (pend ? ` Waiting for payment on invoice ${pend}.` : ' Pick a plan below, and we\'ll email you an invoice.');
    }
  }

  function show(id) {
    ['formBox', 'upBox', 'salesBox', 'doneBox'].forEach(x => $(x).classList.toggle('hidden', x !== id));
    if (id) setTimeout(() => $(id).scrollIntoView({ behavior: 'smooth', block: 'start' }), 30);
  }
  function closeForms() { show(null); window.scrollTo({ top: 0, behavior: 'smooth' }); }

  function choose(key) {
    chosen = key;
    const p = info.plans.find(x => x.key === key);
    if (key === 'enterprise') { show('salesBox'); setTimeout(() => $('eName').focus(), 400); return; }
    if (CAN_UPGRADE) {
      if (key === 'free') return;
      $('uPlanChip').textContent = `${LOOK[key].emoji} ${p.label} · $${p.price} / year`;
      $('uText').textContent = `We'll email you an invoice for $${p.price} with payment instructions. Your account switches to ${p.label} for one year as soon as your payment is received. Nothing changes until then.`;
      show('upBox'); return;
    }
    $('fPlanChip').className = 'chip ' + (key === 'pro' ? 'chip-gold' : key === 'basic' ? 'chip-cyan' : 'chip-green');
    $('fPlanChip').textContent = `${LOOK[key].emoji} ${p.label} plan${p.price ? ` · $${p.price} / year` : ' · free'}`;
    $('fPaidNote').classList.toggle('hidden', !p.price);
    $('fPaidNote').innerHTML = p.price ? `🧾 <b>How paying works:</b> your account opens <b>right away</b> on the Free plan so you can start building. We'll email an invoice for <b>$${p.price}</b> with payment instructions. As soon as payment is received, your account switches to <b>${p.label}</b> for one year.` : '';
    $('sGoLabel').textContent = p.price ? `Create account & email my invoice` : 'Create my free account';
    show('formBox'); setTimeout(() => $('sName').focus(), 400);
  }

  function suggest() {
    if ($('sHandle').dataset.touched) return;
    $('sHandle').value = $('sEmail').value.toLowerCase().replace(/@.*$/, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30);
  }

  async function submit() {
    if ($('sPw').value !== $('sPw2').value) { RQ.toast('The two passwords don\'t match.', 'error'); return; }
    if (!$('sAgree').checked) { RQ.toast('Please tick the box to continue.', 'error'); return; }
    $('sGo').disabled = true;
    const r = await RQ.api('signup', { plan: chosen, name: $('sName').value, org: $('sOrg').value, email: $('sEmail').value, handle: $('sHandle').value,
      password: $('sPw').value, password2: $('sPw2').value, agree: '1', website: $('sWebsite').value });
    $('sGo').disabled = false;
    if (!r.ok) { RQ.toast(r.error, 'error'); return; }
    const email = RQ.esc($('sEmail').value);
    const invoice = r.invoice ? `
      <div class="glass-2 p-4 text-left space-y-2">
        <p class="font-bold">🧾 Invoice ${RQ.esc(r.invoice)} · ${RQ.esc(r.amount)}</p>
        <p class="text-sm text-muted">${r.emailed ? `We emailed it to <b class="text-white">${email}</b> with payment instructions.` : `We couldn't email it just now, so please open it here and keep the link.`}
          Your account switches to ${RQ.esc(info.plans.find(p => p.key === r.plan).label)} as soon as payment is received.</p>
        <a class="btn btn-sm" href="${RQ.esc(r.invoiceUrl)}" target="_blank"><i class="fa-solid fa-file-invoice-dollar"></i> View / print invoice</a>
      </div>` : '';
    $('doneBox').innerHTML = `
      <div class="text-7xl animate-pop-in">🎉</div>
      <h2 class="font-display text-4xl font-bold">You're in!</h2>
      <p class="text-muted text-lg">Your account is ready${r.invoice ? ' on the Free plan for now' : ''}.</p>
      ${invoice}
      <div class="glass-2 p-4 text-left space-y-1 text-sm">
        <p><span class="text-muted">Username:</span> <b>${email}</b></p>
        <p><span class="text-muted">Players' link:</span> <b class="text-cyan-200 break-all">${RQ.esc(r.joinLink)}</b></p>
      </div>
      <p class="text-sm text-muted">Next: sign in and set up an authenticator app on your phone (about one minute).</p>
      <a class="btn btn-primary btn-lg" href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Sign in now</a>`;
    show('doneBox');
    if (typeof confetti === 'function') confetti({ particleCount: 140, spread: 90, origin: { y: .5 } });
  }

  async function upgrade() {
    $('uGo').disabled = true;
    const r = await RQ.api('request_upgrade', { plan: chosen, email: $('uEmail').value });
    $('uGo').disabled = false;
    if (!r.ok) { RQ.toast(r.error, 'error'); return; }
    $('doneBox').innerHTML = `
      <div class="text-7xl animate-pop-in">🧾</div>
      <h2 class="font-display text-3xl font-bold">Invoice ${RQ.esc(r.invoice)} is ready</h2>
      <p class="text-muted">${r.emailed ? `We emailed it to <b class="text-white">${RQ.esc(r.email)}</b>.` : `We couldn't email it just now. Please open it here and keep the link.`}
        Your plan upgrades as soon as your payment of <b class="text-white">${RQ.esc(r.amount)}</b> is received.</p>
      <div class="flex flex-wrap justify-center gap-2">
        <a class="btn btn-primary" href="${RQ.esc(r.invoiceUrl)}" target="_blank"><i class="fa-solid fa-file-invoice-dollar"></i> View / print invoice</a>
        <a class="btn" href="setup.php"><i class="fa-solid fa-arrow-left"></i> Back to my quizzes</a></div>`;
    show('doneBox');
  }

  async function sales() {
    $('eGo').disabled = true;
    const r = await RQ.api('sales_request', { name: $('eName').value, org: $('eOrg').value, email: $('eEmail').value, phone: $('ePhone').value,
      size: $('eSize').value, times: $('eTimes').value, message: $('eMsg').value, website: $('eWebsite').value });
    $('eGo').disabled = false;
    if (!r.ok) { RQ.toast(r.error, 'error'); return; }
    $('doneBox').innerHTML = `
      <div class="text-7xl animate-pop-in">📅</div>
      <h2 class="font-display text-3xl font-bold">Thanks! We'll be in touch.</h2>
      <p class="text-muted">We'll reach out within one business day to set up your meeting${r.emailed ? ', and we sent a confirmation to your email' : ''}.</p>
      ${r.salesLink ? `<a class="btn btn-primary" href="${RQ.esc(r.salesLink)}" target="_blank"><i class="fa-solid fa-calendar-check"></i> Or pick a time now</a>` : ''}`;
    show('doneBox');
  }

  document.addEventListener('DOMContentLoaded', load);
  return { choose, closeForms, suggest, submit, upgrade, sales };
})();
</script>
</body>
</html>
