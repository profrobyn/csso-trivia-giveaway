/* RizQuiz setup page logic: event, question banks, prizes, settings, launch, players, account. */
window.A = (function () {
  const $ = id => document.getElementById(id);
  let data = null;      // admin_data
  let live = null;      // { phase, qIndex, launched } of my game
  let qs = [];          // question bank


  async function call(action, payload = {}, quiet = false) {
    const r = await RQ.api(action, payload);
    if (!r.ok) {
      if (r.code === 'AUTH') { RQ.toast(r.error, 'error'); setTimeout(() => location.reload(), 1200); }
      else if (!quiet) RQ.toast(r.error, 'error');
    }
    return r;
  }

  /* ------------------------------------------------------------ steps (hash routing) */
  const PAGES = ['event', 'questions', 'prizes', 'settings', 'launch', 'players', 'org', 'account'];
  function route() {
    let p = location.hash.replace('#', '');
    if (!PAGES.includes(p)) p = data && qs.length ? 'launch' : 'event';
    document.querySelectorAll('[data-page]').forEach(el => el.classList.toggle('hidden', el.dataset.page !== p));
    document.querySelectorAll('[data-nav]').forEach(a => {
      const on = a.dataset.nav === p;
      a.style.background = on ? 'linear-gradient(135deg,rgba(255,79,216,.25),rgba(139,92,246,.25))' : '';
      a.style.boxShadow = on ? 'inset 0 0 0 1px rgba(255,255,255,.2)' : '';
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (p === 'players' || p === 'launch') loadData();
    if (p === 'org') teamLoad();
  }

  /* ------------------------------------------------------------ data */
  async function loadData() {
    const r = await call('admin_data', {}, true);
    if (!r.ok) return;
    applyData(r);
  }
  function applyData(r) {
    const first = !data;
    data = r;
    live = { phase: r.phase, qIndex: r.qIndex, launched: r.launched };
    qs = r.questions || [];
    document.title = 'Setup — ' + r.config.title;
    RQ.applyBrand(r.brand);
    renderChecklist(); renderQuestions(); renderBanks(); fillSettings(); renderEvent(); renderPlayers(); renderWinLog(); renderAccount();
    renderPlan(); renderBrandEditor(); fillSounds();
    $('navOrg').classList.toggle('hidden', !(r.plan.team && !r.orgMember));
    if (first) { resetForm(); route(); }
  }

  /* ------------------------------------------------------------ launch */
  function renderChecklist() {
    const d = data, url = d.joinUrl;
    const m = RQ.MODE[d.config.prizeMode];
    const items = [
      [qs.length > 0, `<b>${qs.length} question${qs.length === 1 ? '' : 's'}</b> loaded from “${RQ.esc(d.bank.name)}”`, qs.length ? '' : 'Add or load questions first.', '#questions'],
      [true, `Event: <b>${RQ.esc(d.event.name)}</b> · ${RQ.esc(d.session.label)}`, `${d.players.length} player${d.players.length === 1 ? '' : 's'} already in this event`, '#event'],
      [true, `Prizes: <b>${d.config.prizeEveryQuestion ? 'after every question' : 'when you click Winner!'}</b> · ${m.emoji} ${m.label}`, '', '#prizes'],
      [!d.joinIsLocal, `Players join at <b class="break-all">${RQ.esc(url)}</b>`, d.joinIsLocal
        ? `⚠️ Phones can't open “localhost”. Open this page with your computer's network address (like http://192.168.1.25/…) or set a join web address in Game settings.` : 'This link (and its QR code) is unique to your account.', '#settings'],
    ];
    $('checklist').innerHTML = `<h3 class="font-display text-xl font-bold mb-2">Pre-flight checklist</h3>` + items.map(([ok, title, sub, href]) => `
      <a href="${href}" class="flex items-start gap-3 p-3 rounded-2xl hover:bg-white/5 transition">
        <span class="w-9 h-9 shrink-0 rounded-xl grid place-items-center ${ok ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300'}"><i class="fa-solid ${ok ? 'fa-check' : 'fa-exclamation'}"></i></span>
        <span class="min-w-0"><span class="block">${title}</span>${sub ? `<span class="block text-xs ${ok ? 'text-muted' : 'text-amber-200'} mt-0.5">${sub}</span>` : ''}</span>
        <i class="fa-solid fa-chevron-right text-muted ml-auto mt-2 text-xs"></i></a>`).join('');
    document.querySelector('[data-ok="questions"]').classList.toggle('hidden', !qs.length);
    document.querySelector('[data-ok="event"]').classList.remove('hidden');
    document.querySelector('[data-ok="launch"]').classList.toggle('hidden', !d.launched);
    $('railLive').classList.toggle('hidden', !d.launched);
    $('launchState').innerHTML = d.launched
      ? `<p class="chip chip-green mx-auto"><span class="live-dot"></span> Live now · code <b>${d.gameCode}</b></p><p class="text-muted text-sm mt-2">Players can join. Run questions from the live screen.</p>`
      : `<h3 class="font-display text-2xl font-bold">Launch when you're ready</h3><p class="text-muted text-sm">Your game code will be <b class="text-white">${d.gameCode}</b>.</p>`;
    $('launchBtn').innerHTML = d.launched ? '<i class="fa-solid fa-tower-broadcast"></i> Go to live screen' : '<i class="fa-solid fa-rocket"></i> Launch game';
    $('launchBtn').disabled = !qs.length;
    $('closeBtn').classList.toggle('hidden', !d.launched);
  }
  async function launch() {
    if (data.launched) { location.href = 'live.php'; return; }
    const r = await call('launch');
    if (r.ok) {
      if (typeof confetti === 'function') confetti({ particleCount: 160, spread: 100, origin: { y: .6 } });
      setTimeout(() => location.href = 'live.php', 700);
    }
  }
  async function closeGame() {
    if (!confirm('Close the game? Players will see "See you soon" and nobody new can join until you launch again. Scores are kept.')) return;
    const r = await call('close_game');
    if (r.ok) { RQ.toast('Game closed', 'ok'); loadData(); }
  }

  /* ------------------------------------------------------------ account */
  function renderAccount() {
    const a = data.me;
    $('aBackupLeft').textContent = a.backupCodesLeft;
    $('acctCard').innerHTML = `
      <div class="flex items-center gap-3"><span class="w-14 h-14 rounded-2xl grid place-items-center text-2xl font-bold" style="background:linear-gradient(135deg,var(--pink),var(--violet))">${RQ.esc((a.name || a.username).slice(0, 1).toUpperCase())}</span>
        <div><p class="font-display text-2xl font-bold">${RQ.esc(a.name)}</p><p class="text-muted text-sm">${RQ.esc(a.username)}${a.org ? ' · ' + RQ.esc(a.org) : ''}</p></div></div>
      <div class="divider"></div>
      <div class="grid grid-cols-2 gap-2 text-sm">
        <span class="text-muted">Role</span><span class="font-semibold">${a.isMaster ? '👑 Master admin' : 'Host admin'}</span>
        <span class="text-muted">Player link</span><span class="font-semibold text-cyan-200 break-all">${RQ.esc(data.longJoinUrl)}</span>
        <span class="text-muted">Game code</span><span class="font-semibold tracking-widest">${a.gameCode}</span>
        ${a.phone ? `<span class="text-muted">Phone</span><span class="font-semibold">${RQ.esc(a.phone)}</span>` : ''}
        <span class="text-muted">License</span><span class="font-semibold">${a.isMaster ? 'Unlimited' : a.expiresAt ? 'Until ' + new Date(a.expiresAt).toLocaleDateString() : 'No end date'}</span>
      </div>`;
  }
  async function changePassword() {
    const r = await call('change_password', { current: $('aCur').value, password: $('aNew').value, password2: $('aNew2').value });
    if (r.ok) { ['aCur', 'aNew', 'aNew2'].forEach(id => $(id).value = ''); RQ.toast('Password updated', 'ok'); }
  }
  async function newBackupCodes() {
    const r = await call('new_backup_codes', { code: $('aCode').value });
    if (r.ok) {
      $('aCode').value = '';
      $('aCodes').innerHTML = r.backupCodes.map(c => `<span class="glass-2 py-2">${c}</span>`).join('') + '<p class="col-span-2 help">Save these now — they replace your old codes and won\'t be shown again.</p>';
      loadData();
    }
  }

  /* ------------------------------------------------------- questions */
  function summaryOf(q) {
    const t = q.type || 'mc';
    if (t === 'mc' || t === 'tf') return `<span>✅ ${RQ.esc(q.options[q.correctIndex])}</span>`;
    if (t === 'typeans') return `<span>✅ ${RQ.esc((q.accepted || []).join(' / '))}</span>`;
    if (t === 'estimate') return `<span>✅ ${q.answer}${q.unit ? ' ' + RQ.esc(q.unit) : ''}${q.tolerance ? ' ±' + q.tolerance : ''}</span>`;
    if (t === 'ranking') return `<span>✅ ${RQ.esc((q.items || []).join(' → '))}</span>`;
    if (t === 'poll') return `<span>${q.options.length} choices</span>`;
    if (t === 'rating') return `<span>1–${q.scale}</span>`;
    if (t === 'ideas') return `<span>${q.voteTime}s voting · ${q.removeAfter || 'no'} 👎 removal</span>`;
    return '';
  }
  /* The question form lives in its "home" spot; while editing it moves directly below the question being edited. */
  function placeForm() {
    const form = $('qForm'), idx = parseInt($('editIdx').value, 10), row = idx >= 0 ? $('qRow' + idx) : null;
    if (row) {
      row.after(form);
      form.classList.add('ring-glow', 'rise-in');
    } else {
      $('formHome').appendChild(form);
      form.classList.remove('ring-glow');
    }
  }
  function renderQuestions() {
    $('formHome').appendChild($('qForm'));   // move the form out before redrawing the list
    $('qCount').textContent = qs.length;
    const term = (($('qSearch') && $('qSearch').value) || '').toLowerCase().trim();
    const shown = qs.filter(q => !term || (q.name || '').toLowerCase().includes(term) || q.text.toLowerCase().includes(term)).length;
    if (qs.length && !shown) { $('qList').innerHTML = `<p class="text-xs text-slate-400 italic py-4 text-center">No questions match “${RQ.esc(term)}”.</p>`; placeForm(); return; }
    $('qList').innerHTML = qs.length ? qs.map((q, i) => {
      if (term && !((q.name || '').toLowerCase().includes(term) || q.text.toLowerCase().includes(term))) return '';
      const pm = q.prizeMode && q.prizeMode !== 'default' ? `<span class="px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-700">${RQ.MODE[q.prizeMode].short}</span>` : '';
      const editing = parseInt($('editIdx').value, 10) === i;
      return `<div id="qRow${i}" class="scroll-mt-24 p-3 rounded-xl border flex items-center justify-between gap-2 text-xs ${editing ? 'bg-cyan-500/10 border-cyan-300/60' : 'bg-black/25 border-white/10'}">
        <div class="min-w-0 flex-1 space-y-0.5">
          <p class="font-bold text-white truncate"><span class="text-amber-300 mr-1">#${i + 1}</span>${q.name ? `<span class="px-1.5 py-0.5 mr-1 rounded bg-sky-950 text-sky-300 border border-sky-700 text-[10px] font-black">${RQ.esc(q.name)}</span>` : ''}${RQ.esc(q.text)}</p>
          <p class="text-[10px] text-slate-400 flex flex-wrap gap-2 items-center"><span class="chip chip-cyan !py-0 !text-[10px]">${(RQT.INFO[q.type || 'mc'] || RQT.INFO.mc).icon} ${(RQT.INFO[q.type || 'mc'] || RQT.INFO.mc).label}</span>${summaryOf(q)}<span>${q.prizeMode === 'none' ? '🚫 no prize' : '🎁 prize'}</span><span>⏱ ${q.timeLimit}s</span>${pm}${q.note ? '<span title="' + RQ.esc(q.note) + '">💬 comment</span>' : ''}</p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <button onclick="A.moveQ(${i},-1)" class="p-1.5 ${i === 0 ? 'opacity-20' : 'text-slate-300 hover:text-amber-300'}" ${i === 0 ? 'disabled' : ''}><i class="fa-solid fa-arrow-up"></i></button>
          <button onclick="A.moveQ(${i},1)" class="p-1.5 ${i === qs.length - 1 ? 'opacity-20' : 'text-slate-300 hover:text-amber-300'}" ${i === qs.length - 1 ? 'disabled' : ''}><i class="fa-solid fa-arrow-down"></i></button>
          <button onclick="A.editQ(${i})" class="p-1.5 text-emerald-400" title="Edit"><i class="fa-solid fa-pen"></i></button>
          <button onclick="A.dupQ(${i})" class="p-1.5 text-sky-400" title="Duplicate"><i class="fa-solid fa-clone"></i></button>
          <button onclick="A.delQ(${i})" class="p-1.5 text-rose-400" title="Delete"><i class="fa-solid fa-trash"></i></button>
        </div></div>`;
    }).join('') : `<p class="text-xs text-slate-400 italic py-4 text-center">No questions yet. Add one above or load a JSON file.</p>`;
    placeForm();
  }

  async function saveQs(msg) {
    const r = await call('save_questions', { questions: qs });
    if (r.ok) { qs = r.questions; data.questions = qs; data.banks = r.banks; renderQuestions(); renderBanks(); renderChecklist(); if (msg) RQ.toast(msg, 'ok'); }
    return r.ok;
  }

  /* --------------------------------------------------- question banks */
  const currentBankId = () => data ? data.bank.id : 0;
  const gameInProgress = () => live && (live.phase !== 'LOBBY' || live.qIndex >= 0);
  let openPreview = null;

  function renderBanks() {
    if (!data) return;
    $('curBankName').textContent = data.bank.name;
    $('curBankCount').textContent = `· ${qs.length} question${qs.length === 1 ? '' : 's'}`;
    $('qBankName').textContent = data.bank.name;
    const s = ($('bankSearch').value || '').toLowerCase();
    const list = data.banks.filter(b => !s || b.name.toLowerCase().includes(s) || b.preview.some(t => t.toLowerCase().includes(s)));
    $('bankList').innerHTML = list.length ? list.map(b => `
      <div class="rounded-xl border ${b.active ? 'border-amber-300 bg-white/10' : 'border-white/10 bg-black/25'} p-3 space-y-2">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-sm font-black text-white truncate">${b.active ? '<i class="fa-solid fa-circle-check text-amber-300"></i> ' : ''}${RQ.esc(b.name)}</p>
            <p class="text-[11px] text-slate-400">${b.count} question${b.count === 1 ? '' : 's'} · updated ${new Date(b.updated).toLocaleDateString()}</p>
          </div>
          ${b.active ? '<span class="text-[10px] font-black text-amber-300 shrink-0">LOADED</span>'
            : `<button class="btn btn-gold !py-1 !px-3 shrink-0" onclick="A.useBank(${b.id})"><i class="fa-solid fa-upload"></i> Load</button>`}
        </div>
        ${b.preview.length ? `<p class="text-[11px] text-slate-400 truncate">${b.preview.map(t => RQ.esc(t)).join(' · ')}</p>` : ''}
        <div class="flex flex-wrap gap-1.5">
          <button class="btn btn-sm" onclick="A.previewBank(${b.id})"><i class="fa-solid fa-eye"></i> ${openPreview === b.id ? 'Hide' : 'View'}</button>
          ${b.active ? '' : `<button class="btn btn-sm" onclick="A.appendBank(${b.id})" title="Copy this bank's questions onto the end of the loaded bank"><i class="fa-solid fa-plus"></i> Add to Loaded</button>`}
          <button class="btn btn-sm" onclick="A.renameBank(${b.id})"><i class="fa-solid fa-pen"></i></button>
          <button class="btn btn-sm" onclick="A.dupBank(${b.id})" title="Duplicate"><i class="fa-solid fa-clone"></i></button>
          ${b.active ? '' : `<button class="btn btn-sm !text-rose-300" onclick="A.deleteBank(${b.id})" title="Delete"><i class="fa-solid fa-trash"></i></button>`}
        </div>
        <div id="bankPrev${b.id}" class="${openPreview === b.id ? '' : 'hidden'} text-[11px] text-slate-300 space-y-1 max-h-48 overflow-y-auto custom-scrollbar"></div>
      </div>`).join('') : `<p class="text-xs text-slate-400 italic p-3">No banks match.</p>`;
    if (openPreview) fillPreview(openPreview);
  }
  async function fillPreview(id) {
    const el = $('bankPrev' + id); if (!el) return;
    const r = await call('bank_view', { id }, true);
    if (!r.ok) return;
    el.innerHTML = r.bank.questions.map((q, i) => `<p><span class="text-amber-300 font-bold">${i + 1}.</span> ${RQ.esc(q.text)} <span class="text-emerald-400">✓ ${RQ.esc(q.options[q.correctIndex])}</span></p>`).join('') || '<p class="italic">Empty bank.</p>';
  }
  function previewBank(id) { openPreview = openPreview === id ? null : id; renderBanks(); }

  async function useBank(id) {
    if (!id || id === currentBankId()) { RQ.toast('That bank is already loaded.', 'info'); return; }
    const b = data.banks.find(x => x.id === id);
    if (live && live.phase === 'QUESTION') { RQ.toast('Wait for the current question to end before switching banks.', 'error'); renderBanks(); return; }
    if (gameInProgress() && !confirm(`Load “${b.name}”? The game will return to the lobby and start again from question 1 of this bank. Scores already earned are kept.`)) { renderBanks(); return; }
    const r = await call('bank_activate', { id });
    if (r.ok) { applyData(r); resetForm(); RQ.toast(`Loaded “${b.name}” (${b.count} questions)`, 'ok'); }
    else renderBanks();
  }
  async function appendBank(id) {
    const b = data.banks.find(x => x.id === id);
    if (!confirm(`Add the ${b.count} questions from “${b.name}” to the end of “${data.bank.name}”? (“${b.name}” itself is not changed.)`)) return;
    const r = await call('bank_append', { id });
    if (r.ok) { applyData(r); RQ.toast(`Added ${b.count} questions`, 'ok'); }
  }
  async function renameBank(id) {
    const b = data.banks.find(x => x.id === id);
    const n = prompt('Rename question bank:', b.name);
    if (!n || n.trim() === b.name) return;
    const r = await call('bank_rename', { id, name: n.trim() });
    if (r.ok) { applyData(r); RQ.toast('Renamed', 'ok'); }
  }
  async function dupBank(id) {
    const r = await call('bank_duplicate', { id });
    if (r.ok) { applyData(r); RQ.toast('Copy saved', 'ok'); }
  }
  async function deleteBank(id) {
    const b = data.banks.find(x => x.id === id);
    if (!confirm(`Delete the question bank “${b.name}” (${b.count} questions)? This can't be undone.`)) return;
    const r = await call('bank_delete', { id });
    if (r.ok) { applyData(r); RQ.toast('Bank deleted', 'ok'); }
  }
  async function newBank() {
    const n = prompt('Name for the new, empty question bank:', `Question Bank ${data.banks.length + 1}`);
    if (!n) return;
    if (live && live.phase === 'QUESTION') { RQ.toast('Wait for the current question to end.', 'error'); return; }
    if (gameInProgress() && !confirm('The new bank will be loaded and the game will return to the lobby. Continue?')) return;
    const r = await call('bank_create', { name: n.trim(), questions: [], activate: '1' });
    if (r.ok) { applyData(r); resetForm(); RQ.toast('New bank created — add questions below', 'ok'); }
  }

  /* ---------------- question editor (all types) ---------------- */
  const TYPE_ORDER = ['mc', 'tf', 'typeans', 'estimate', 'ranking', 'poll', 'rating', 'wordcloud', 'ideas'];
  const TYPE_HELP = {
    mc: 'Classic quiz: 2–6 answers, one is right. Faster correct answers score more.',
    tf: 'A statement that is true or false. Fast and fun warm-ups.',
    typeans: 'Players type the answer. Spelling of capitals/accents/punctuation is forgiven.',
    estimate: 'Players guess a number with a slider. Within the ± range counts as correct; the big screen shows the closest guesses.',
    ranking: 'Players tap items into the right order.',
    poll: 'Opinion vote with live bar chart. No right answer.',
    rating: 'Players rate 1–5 or 1–10. Shows the average live.',
    wordcloud: 'Players send short words; the cloud grows live on screen.',
    ideas: 'Players share ideas, then nominate the best one (⭐) and vote down ideas that miss (👎). Too many 👎 removes an idea. The best idea and the voters earn points.',
  };
  const curType = () => $('fType').value;
  function renderTypePicker() {
    $('typePicker').innerHTML = TYPE_ORDER.map(t => `<button type="button" data-t="${t}" class="pick !p-2 !gap-1.5 flex-col items-center text-center ${curType() === t ? 'on' : ''}">
      <span class="text-xl leading-none">${RQT.INFO[t].icon}</span><span class="text-[11px] font-bold leading-tight">${RQT.INFO[t].label}</span></button>`).join('');
    $('typePicker').querySelectorAll('button').forEach(b => b.onclick = () => setType(b.dataset.t));
  }
  function setType(t) {
    $('fType').value = t;
    renderTypePicker();
    document.querySelectorAll('#qForm [data-for]').forEach(el => el.classList.toggle('hidden', !el.dataset.for.split(' ').includes(t)));
    $('typeHelp').textContent = TYPE_HELP[t];
    $('fTextLbl').textContent = t === 'tf' ? 'Statement' : t === 'ideas' ? 'What should people share ideas about?' : 'Question';
    $('fTimeLbl').textContent = t === 'ideas' ? 'Seconds to share ideas' : 'Seconds to answer';
    if (t === 'ideas' && +$('fTime').value < 30) $('fTime').value = 60;
    const scored = QTYPE_SCORED[t], keep = $('fPrize').value;
    $('fPrize').innerHTML = `<option value="default">Default type (${RQ.MODE[(data && data.config.prizeMode) || 'random_correct'].short})</option>
      <option value="random_correct">🎲 ${scored ? 'Random correct answer' : 'Random participant'}</option>
      <option value="random_player">🎟️ Random player</option>
      <option value="fastest">${t === 'ideas' ? '💡 Best idea' : scored ? '⚡ Fastest correct answer' : '⚡ First response'}</option>`;
    $('fPrize').value = keep && keep !== 'none' ? keep : 'default';
  }
  const QTYPE_SCORED = { mc: 1, tf: 1, typeans: 1, estimate: 1, ranking: 1, poll: 0, rating: 0, wordcloud: 0, ideas: 0 };
  function prizeToggle() { $('fPrize').disabled = !$('fPrizeOn').checked; }
  const lines = id => $(id).value.split('\n').map(x => x.trim()).filter(Boolean);

  function resetForm() {
    $('editIdx').value = -1;
    ['fName', 'fNote', 'fText', 'fO0', 'fO1', 'fO2', 'fO3', 'fO4', 'fO5', 'fAccepted', 'fAns', 'fUnit', 'fItems', 'fBestPts', 'fVoterPts'].forEach(id => $(id).value = '');
    $('fCorrect').value = 0; $('fTol').value = 0; $('fMin').value = 0; $('fMax').value = 100; $('fStep').value = 1; $('fPoints').value = 0;
    $('fScale').value = 5; $('fLow').value = 'Not at all'; $('fHigh').value = 'Absolutely'; $('fMaxEntries').value = 3;
    $('fMaxIdeas').value = 2; $('fVoteTime').value = 60; $('fVotesPer').value = 1; $('fRemoveAfter').value = 3;
    document.querySelector('input[name=fTF][value="0"]').checked = true;
    $('fPrizeOn').checked = true; prizeToggle();
    $('fTime').value = data ? data.config.defaultTime : 15;
    setType(curType() || 'mc');
    $('formTitle').innerHTML = '<i class="fa-solid fa-plus-circle text-pink-400"></i> Add a question';
    $('cancelEdit').classList.add('hidden');
    if (qs.length) renderQuestions(); else placeForm();
  }
  function saveQuestion() {
    const t = curType(), text = $('fText').value.trim();
    if (!text) { RQ.toast('Please type the question.', 'error'); return; }
    const idx = parseInt($('editIdx').value, 10);
    const q = { id: idx >= 0 ? qs[idx].id : 'q_' + Date.now(), type: t, name: $('fName').value.trim(), note: $('fNote').value.trim(), text,
      timeLimit: parseInt($('fTime').value, 10) || 15, prizeMode: $('fPrizeOn').checked ? $('fPrize').value : 'none' };
    if (t === 'mc' || t === 'poll') {
      const raw = [0, 1, 2, 3, 4, 5].map(i => $('fO' + i).value.trim());
      const ci = parseInt($('fCorrect').value, 10);
      if (t === 'mc' && !raw[ci]) { RQ.toast('The correct answer is empty.', 'error'); return; }
      const opts = []; let newCi = 0;
      raw.forEach((o, i) => { if (o) { if (i === ci) newCi = opts.length; opts.push(o); } });
      if (opts.length < 2) { RQ.toast('Please give at least two answers.', 'error'); return; }
      q.options = opts; if (t === 'mc') q.correctIndex = newCi;
    } else if (t === 'tf') {
      q.options = ['True', 'False']; q.correctIndex = +document.querySelector('input[name=fTF]:checked').value;
    } else if (t === 'typeans') {
      q.accepted = lines('fAccepted'); if (!q.accepted.length) { RQ.toast('Add at least one accepted answer.', 'error'); return; }
    } else if (t === 'estimate') {
      if ($('fAns').value === '') { RQ.toast('Enter the correct number.', 'error'); return; }
      Object.assign(q, { answer: +$('fAns').value, tolerance: +$('fTol').value || 0, unit: $('fUnit').value.trim(), min: +$('fMin').value, max: +$('fMax').value, step: +$('fStep').value || 1 });
      if (q.max <= q.min) { RQ.toast('“Slider to” must be bigger than “Slider from”.', 'error'); return; }
      if (q.answer < q.min || q.answer > q.max) { RQ.toast('The correct number must be inside the slider range.', 'error'); return; }
    } else if (t === 'ranking') {
      q.items = lines('fItems'); if (q.items.length < 3 || q.items.length > 6) { RQ.toast('Use 3 to 6 items, one per line.', 'error'); return; }
    } else if (t === 'rating') {
      Object.assign(q, { scale: +$('fScale').value, lowLabel: $('fLow').value.trim(), highLabel: $('fHigh').value.trim() });
    } else if (t === 'wordcloud') {
      q.maxEntries = +$('fMaxEntries').value;
    } else if (t === 'ideas') {
      Object.assign(q, { maxIdeas: +$('fMaxIdeas').value || 2, voteTime: +$('fVoteTime').value || 60, votesPerPlayer: +$('fVotesPer').value || 1,
        removeAfter: +$('fRemoveAfter').value || 0, bestIdeaPoints: $('fBestPts').value === '' ? -1 : +$('fBestPts').value, voterPoints: $('fVoterPts').value === '' ? -1 : +$('fVoterPts').value });
    }
    if (['poll', 'rating', 'wordcloud'].includes(t)) q.points = +$('fPoints').value || 0;
    if (idx >= 0) qs[idx] = q; else qs.push(q);
    saveQs(idx >= 0 ? 'Question updated' : 'Question added').then(ok => ok && resetForm());
  }
  function editQ(i) {
    const q = qs[i], t = q.type || 'mc';
    resetForm();
    $('editIdx').value = i; $('fText').value = q.text; $('fName').value = q.name || ''; $('fNote').value = q.note || '';
    setType(t);
    $('fTime').value = q.timeLimit;
    $('fPrizeOn').checked = q.prizeMode !== 'none'; prizeToggle();
    $('fPrize').value = q.prizeMode && q.prizeMode !== 'none' ? q.prizeMode : 'default';
    if (q.options) [0, 1, 2, 3, 4, 5].forEach(j => $('fO' + j).value = q.options[j] || '');
    if ((q.options || []).length > 4) document.querySelectorAll('.more-opt').forEach(e => e.classList.remove('hidden'));
    if (t === 'mc') $('fCorrect').value = q.correctIndex;
    if (t === 'tf') document.querySelector(`input[name=fTF][value="${q.correctIndex}"]`).checked = true;
    if (t === 'typeans') $('fAccepted').value = (q.accepted || []).join('\n');
    if (t === 'estimate') { $('fAns').value = q.answer; $('fTol').value = q.tolerance; $('fUnit').value = q.unit || ''; $('fMin').value = q.min; $('fMax').value = q.max; $('fStep').value = q.step; }
    if (t === 'ranking') $('fItems').value = (q.items || []).join('\n');
    if (t === 'rating') { $('fScale').value = q.scale; $('fLow').value = q.lowLabel; $('fHigh').value = q.highLabel; }
    if (t === 'wordcloud') $('fMaxEntries').value = q.maxEntries;
    if (t === 'ideas') { $('fMaxIdeas').value = q.maxIdeas; $('fVoteTime').value = q.voteTime; $('fVotesPer').value = q.votesPerPlayer; $('fRemoveAfter').value = q.removeAfter;
      $('fBestPts').value = q.bestIdeaPoints >= 0 ? q.bestIdeaPoints : ''; $('fVoterPts').value = q.voterPoints >= 0 ? q.voterPoints : ''; }
    $('fPoints').value = q.points || 0;
    $('formTitle').innerHTML = `<i class="fa-solid fa-pen-to-square text-cyan-300"></i> Edit question #${i + 1}`;
    $('cancelEdit').classList.remove('hidden');
    renderQuestions();   // highlights the row and moves the form right below it
    setTimeout(() => $('qRow' + i).scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
  }
  function dupQ(i) { const c = JSON.parse(JSON.stringify(qs[i])); c.id = 'q_' + Date.now(); qs.splice(i + 1, 0, c); saveQs('Duplicated'); }
  function delQ(i) { if (confirm(`Delete question #${i + 1}?`)) { qs.splice(i, 1); saveQs('Deleted'); } }
  function moveQ(i, d) { const j = i + d; if (j < 0 || j >= qs.length) return; [qs[i], qs[j]] = [qs[j], qs[i]]; saveQs(); }
  function applyAllTime() {
    const t = parseInt($('allTime').value, 10);
    if (isNaN(t) || t < 5 || t > 300) { RQ.toast('Enter 5–300 seconds.', 'error'); return; }
    if (!confirm(`Set the timer to ${t}s for all ${qs.length} questions?`)) return;
    qs.forEach(q => q.timeLimit = t); saveQs(`All timers set to ${t}s`);
  }
  function clearQuestions() { if (confirm('Remove ALL questions from the bank? (Export a JSON backup first if you need it.)')) { qs = []; saveQs('Question bank cleared'); } }

  function parseJsonText(txt) {
    let clean = String(txt).replace(/^﻿/, '').trim().replace(/,\s*([\]}])/g, '$1');
    let j = JSON.parse(clean);
    if (!Array.isArray(j)) j = j.questions || j.data || j.items;
    if (!Array.isArray(j)) throw new Error('Expected a list of questions');
    return j;
  }
  // mode: 'new' (blank box to paste a new bank) or 'view' (edit the loaded bank as JSON)
  function openJson(mode) {
    $('jsonText').value = mode === 'view' ? JSON.stringify(qs, null, 2) : '';
    $('jsonBankName').value = mode === 'view' ? data.bank.name + ' (edited)' : '';
    $('jsonModal').classList.remove('hidden');
    $(mode === 'view' ? 'jsonText' : 'jsonBankName').focus();
  }
  function closeJson() { $('jsonModal').classList.add('hidden'); }
  async function applyJson(how) {
    let list;
    try { list = parseJsonText($('jsonText').value); } catch (e) { RQ.toast('Invalid JSON: ' + e.message, 'error'); return; }
    if (how === 'new') {
      const name = $('jsonBankName').value.trim();
      if (!name) { RQ.toast('Give the new bank a name.', 'error'); $('jsonBankName').focus(); return; }
      if (live && live.phase === 'QUESTION') { RQ.toast('Wait for the current question to end.', 'error'); return; }
      if (gameInProgress() && !confirm('The new bank will be loaded and the game will return to the lobby. Continue?')) return;
      const r = await call('bank_create', { name, questions: list, activate: '1' });
      if (r.ok) { applyData(r); resetForm(); closeJson(); RQ.toast(`Saved “${name}” with ${r.questions.length} questions and loaded it`, 'ok'); }
      return;
    }
    if (how === 'replace' && !confirm(`Replace all ${qs.length} questions in “${data.bank.name}” with these ${list.length}?`)) return;
    const before = qs.length;
    qs = how === 'append' ? qs.concat(list) : list;
    saveQs().then(ok => { if (ok) { closeJson(); RQ.toast(how === 'append' ? `Added ${qs.length - before} questions` : `Replaced with ${qs.length} questions`, 'ok'); } });
  }
  // every uploaded file becomes its own saved bank (named after the file)
  async function loadFile(input) {
    const files = [...input.files]; input.value = '';
    if (!files.length) return;
    const parsed = [];
    for (const f of files) {
      const txt = await f.text();
      try { parsed.push({ name: f.name.replace(/\.json$/i, '').replace(/[_-]+/g, ' ').trim() || f.name, list: parseJsonText(txt) }); }
      catch (e) { RQ.toast(`${f.name} is not valid question JSON — skipped.`, 'error'); }
    }
    if (!parsed.length) return;
    const single = parsed.length === 1;
    let activate = false;
    if (single) {
      activate = !(live && live.phase === 'QUESTION') &&
        (!gameInProgress() || confirm(`Save “${parsed[0].name}” as a new bank and load it now? The game will return to the lobby.\n\nCancel = just save it for later.`));
    }
    let r;
    for (const p of parsed) r = await call('bank_create', { name: p.name, questions: p.list, activate: activate ? '1' : '0' });
    if (r && r.ok) {
      applyData(r); resetForm();
      RQ.toast(single ? (activate ? `Loaded “${parsed[0].name}”` : `Saved “${parsed[0].name}” — click Load to use it`) : `Saved ${parsed.length} new banks — click Load on the one you want`, 'ok');
    }
  }
  function exportJson() {
    const blob = new Blob([JSON.stringify(qs, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `${(data.bank.name || 'rizquiz_questions').replace(/[^\w\- ]+/g, '').replace(/\s+/g, '_')}.json`;
    document.body.appendChild(a); a.click(); a.remove();
  }

  /* -------------------------------------------------------- settings */
  function fillSettings() {
    const c = data.config;
    $('cPrizeEvery').checked = c.prizeEveryQuestion; $('cDelay').value = c.autoDrawDelay;
    $('cMixStyle').value = c.mixStyle; $('cRepeat').value = c.repeatWinners; $('cAnnounce').checked = c.announcePrize;
    $('cTitle').value = c.title; $('cDefTime').value = c.defaultTime; $('cLock').value = c.lockFinalN;
    $('cSignupLink').checked = c.showSignupLink || !data.plan.branding; $('cSignupLink').disabled = !data.plan.branding;
    $('cSignupHelp').textContent = data.plan.branding ? 'At the podium, players see a link to create their own free RizQuiz account. You can turn it off.' : 'At the podium, players see a link to create their own free RizQuiz account. Pro accounts can turn this off.';
    $('cScope').value = c.leaderboardScope; $('cAutoEnd').checked = c.autoEndWhenAllAnswered; $('cActive').value = c.activeWindowSec;
    $('cJoinUrl').value = c.joinUrl; $('cWifi').checked = c.showWifiWarning; $('cWifiText').value = c.wifiText;
    $('cShortUrl').value = c.shortUrl || ''; $('cUseShort').checked = !!c.useShortUrl;
    $('cWifiName').value = c.wifiName || ''; $('cWifiPass').value = c.wifiPass || '';
    $('longLink').textContent = data.longJoinUrl + (data.folderOk ? '' : '  (⚠️ could not create this folder — check that PHP can write to the RizQuiz folder)');
    $('joinHint').innerHTML = `Players will use: <span class="text-cyan-200 break-all">${RQ.esc(data.joinUrl)}</span>` +
      (data.joinIsLocal ? '<br><span class="text-rose-300">⚠️ Phones can\'t reach “localhost”. Type your computer\'s network address (run <b>ipconfig</b> → IPv4, e.g. http://192.168.1.25/RizQuiz/) or a public web address.</span>' : '');
    if ($('editIdx').value === '-1' && !$('fText').value) $('fTime').value = c.defaultTime;
    $('modeCards').innerHTML = ['random_correct', 'random_player', 'fastest', 'mix'].map(m => `
      <label class="pick flex-col ${c.prizeMode === m ? 'on' : ''}">
        <input type="radio" name="cMode" value="${m}" ${c.prizeMode === m ? 'checked' : ''} class="hidden" onchange="A.modePicked()">
        <span class="ico">${RQ.MODE[m].emoji}</span>
        <span><span class="block font-display text-lg font-bold">${RQ.MODE[m].label}</span>
        <span class="block text-xs text-muted mt-0.5">${RQ.esc(RQ.MODE[m].rule)}</span></span></label>`).join('');
    $('mixBox').classList.toggle('hidden', c.prizeMode !== 'mix');
  }
  function modePicked() {
    const m = document.querySelector('input[name=cMode]:checked').value;
    document.querySelectorAll('#modeCards label').forEach(l => {
      const on = l.querySelector('input').value === m;
      l.classList.toggle('on', on);
    });
    $('mixBox').classList.toggle('hidden', m !== 'mix');
  }
  async function saveSettings(nextHash) {
    const m = document.querySelector('input[name=cMode]:checked');
    const cfg = {
      prizeEveryQuestion: $('cPrizeEvery').checked, autoDrawDelay: $('cDelay').value, prizeMode: m ? m.value : 'random_correct',
      mixStyle: $('cMixStyle').value, repeatWinners: $('cRepeat').value, announcePrize: $('cAnnounce').checked,
      title: $('cTitle').value, defaultTime: $('cDefTime').value, lockFinalN: $('cLock').value, leaderboardScope: $('cScope').value,
      autoEndWhenAllAnswered: $('cAutoEnd').checked, showSignupLink: $('cSignupLink').checked, activeWindowSec: $('cActive').value, joinUrl: $('cJoinUrl').value.trim(),
      showWifiWarning: $('cWifi').checked, wifiText: $('cWifiText').value,
      shortUrl: $('cShortUrl').value.trim(), useShortUrl: $('cUseShort').checked,
      questionSound: $('cQSound').value, podiumSound: $('cPSound').value,
      wifiName: $('cWifiName').value.trim(), wifiPass: $('cWifiPass').value,
    };
    const r = await call('save_config', { config: cfg });
    if (r.ok) { RQ.toast('Settings saved', 'ok'); await loadData(); if (typeof nextHash === 'string') location.hash = nextHash; }
  }

  async function makeShort() {
    // save the server address first so the short link points to the right place
    const r0 = await call('save_config', { config: { joinUrl: $('cJoinUrl').value.trim() } });
    if (!r0.ok) return;
    const r = await call('make_short_url');
    if (r.ok) { RQ.toast('Short link ready: ' + r.shortUrl, 'ok'); await loadData(); }
  }

  /* ----------------------------------------------- event & players */
  function renderEvent() {
    $('plEvent').textContent = data.event.name;
    $('evName').value = data.event.name;
    $('sessionList').innerHTML = data.sessions.map(s => `
      <span class="chip !text-sm !py-2 !px-3 ${s.id == data.session.id ? 'chip-gold !border-amber-300' : ''}">
        📅 ${RQ.esc(s.label)} <span class="opacity-70 font-semibold">· ${new Date(+s.started_at).toLocaleDateString()} · ${s.played} Qs played</span>
        ${s.id == data.session.id ? ' · CURRENT' : ''}
        <button class="ml-1 opacity-70 hover:opacity-100" onclick="A.renameSession(${s.id}, ${RQ.esc(JSON.stringify(s.label))})" title="Rename"><i class="fa-solid fa-pen text-[10px]"></i></button></span>`).join('');
    $('newSessLabel').placeholder = `e.g. Day ${data.sessions.length + 1}`;
    $('eventList').innerHTML = data.events.map(e => `
      <div class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl text-sm ${e.id == data.event.id ? 'pick on !p-3' : 'glass-2'}">
        <span class="min-w-0"><b class="text-white truncate block">${RQ.esc(e.name)}</b><span class="text-slate-400">${e.players} players · ${e.sessions} day(s)</span></span>
        ${e.id == data.event.id ? '<span class="text-amber-300 font-black text-[10px]">CURRENT</span>' : `<button class="btn btn-sm" onclick="A.switchEvent(${e.id})">Switch</button>`}
      </div>`).join('');
  }
  function renderPlayers() {
    if (!data) return;
    const s = ($('pSearch').value || '').toLowerCase();
    const list = data.players.filter(p => !s || p.name.toLowerCase().includes(s));
    $('pCount').textContent = data.players.length;
    $('pOnline').textContent = data.players.filter(p => p.online).length + ' online';
    $('pTable').innerHTML = list.length ? list.map(p => `
      <tr class="border-t border-white/10 hover:bg-black/25">
        <td class="p-2 text-slate-400">${data.players.indexOf(p) + 1}</td>
        <td class="p-2"><span class="inline-block w-2 h-2 rounded-full mr-1 ${p.online ? 'bg-emerald-400' : 'bg-slate-600'}"></span><span class="text-lg align-middle">${RQ.esc(p.emoji)}</span> <b class="text-white">${RQ.esc(p.name)}</b></td>
        <td class="p-2 text-right font-black text-emerald-400">${RQ.fmt(p.total)}</td>
        <td class="p-2 text-right text-slate-300">${RQ.fmt(p.today)}</td>
        <td class="p-2 text-right text-slate-300">${p.correct}</td>
        <td class="p-2 text-right">${p.wins ? '<span class="text-amber-300 font-bold">🎁 ' + p.wins + '</span>' : '<span class="text-slate-600">0</span>'}</td>
        <td class="p-2 font-mono font-bold text-amber-300 tracking-widest">${RQ.esc(p.code)}</td>
        <td class="p-2 text-right whitespace-nowrap">
          <button class="btn btn-sm !py-1 !px-2.5" title="Rename" onclick="A.renamePlayer(${p.id})"><i class="fa-solid fa-pen"></i><span class="hidden xl:inline"> Rename</span></button>
          <button class="btn btn-sm btn-danger !py-1 !px-2.5" title="Remove this player" onclick="A.deletePlayer(${p.id})"><i class="fa-solid fa-user-xmark"></i> Remove</button>
        </td></tr>`).join('') : `<tr><td colspan="8" class="p-4 text-center text-slate-400 italic">No players yet.</td></tr>`;
    const bl = data.blockedNames || [];
    $('blockedBox').classList.toggle('hidden', !bl.length);
    $('blockedList').innerHTML = bl.map(b => `<span class="chip chip-red">🚫 ${RQ.esc(b.name)}
      <button class="ml-1 opacity-80 hover:opacity-100" title="Allow this name again" onclick="A.unblockName('${RQ.esc(b.key)}')"><i class="fa-solid fa-xmark"></i></button></span>`).join('');
  }
  function renderWinLog() {
    $('winLog').innerHTML = data.draws.length ? data.draws.map(w => `
      <div class="p-2.5 bg-black/25 rounded-lg border border-white/10 flex items-center justify-between gap-2 text-xs">
        <span class="text-white font-semibold">${RQ.esc(w.session || '')} · ${w.qIndex >= 0 ? (w.bank ? RQ.esc(w.bank) + ' ' : '') + 'Q' + (w.qIndex + 1) + (w.qName ? ' “' + RQ.esc(w.qName) + '”' : '') : 'Bonus'} ·
          ${w.name ? RQ.esc(w.emoji) + ' ' + RQ.esc(w.name) : '<span class="text-slate-500">no eligible player' + (w.note === 'all_won' ? ' (all had already won)' : '') + '</span>'}
          ${w.mode === 'fastest' && w.ms !== null ? `<span class="text-slate-400">(${(w.ms / 1000).toFixed(2)}s)</span>` : ''}</span>
        <span class="flex items-center gap-2"><span class="text-[10px] px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-600 font-bold">${RQ.MODE[w.mode].short}${w.auto ? ' · auto' : ''}</span>
        <button class="text-slate-500 hover:text-rose-400" title="Remove from log (lets this person win again)" onclick="A.deleteDraw(${w.id})"><i class="fa-solid fa-xmark"></i></button></span>
      </div>`).join('') : `<p class="text-xs text-slate-400 italic">No prizes awarded yet.</p>`;
  }

  async function act(action, payload, msg) { const r = await call(action, payload); if (r.ok && r.questions !== undefined) { applyData(r); if (msg) RQ.toast(msg, 'ok'); } return r; }
  const renameEvent = () => act('rename_event', { name: $('evName').value }, 'Event renamed');
  const renameSession = (id, label) => { const l = prompt('Rename this day / session:', label); if (l) act('rename_session', { id, label: l }, 'Renamed'); };
  function newSession() {
    const label = $('newSessLabel').value.trim() || $('newSessLabel').placeholder.replace('e.g. ', '');
    if (!confirm(`Start "${label}"? Players stay logged in and their scores keep adding up. The game returns to the lobby.`)) return;
    act('new_session', { label }, `Started ${label}`).then(() => $('newSessLabel').value = '');
  }
  function newEvent() {
    const name = $('newEvName').value.trim();
    if (!name) { RQ.toast('Type a name for the new event.', 'error'); return; }
    if (!confirm(`Create and switch to "${name}"? Everyone will need to join this new event (scores start at zero). Your old event is kept.`)) return;
    act('new_event', { name }, 'New event created').then(() => $('newEvName').value = '');
  }
  const switchEvent = id => { if (confirm('Switch to this event? The game returns to the lobby and phones will rejoin that event.')) act('switch_event', { id }, 'Switched event'); };
  function renamePlayer(id) {
    const p = data.players.find(x => x.id === id);
    const n = prompt('New name for ' + p.name + ':', p.name);
    if (n && n.trim() !== p.name) act('player_update', { id, name: n.trim() }, 'Player renamed');
  }
  function deletePlayer(id) {
    const p = data.players.find(x => x.id === id); if (!p) return;
    const old = document.getElementById('rmModal'); if (old) old.remove();
    const m = document.createElement('div');
    m.id = 'rmModal'; m.className = 'fixed inset-0 z-[90] grid place-items-center p-4'; m.style.background = 'rgba(5,4,20,.75)';
    m.innerHTML = `<div role="dialog" aria-modal="true" class="glass !rounded-3xl w-full max-w-md p-6 space-y-4 animate-pop-in" style="background:rgba(30,12,40,.97);border:1px solid rgba(244,63,94,.6)">
      <div class="flex items-center gap-3"><span class="text-4xl">${RQ.esc(p.emoji)}</span>
        <div class="min-w-0"><h3 class="font-display text-2xl font-bold">Remove ${RQ.esc(p.name)}?</h3><p class="text-sm text-muted">${RQ.fmt(p.total)} pts · ${p.wins ? p.wins + ' prize' + (p.wins > 1 ? 's' : '') : 'no prizes'}</p></div></div>
      <p class="text-sm">This deletes their score, answers, word-cloud words, ideas, votes and any prizes they won. Their phone goes back to the join screen. Other players aren't affected.</p>
      <label class="flex items-start gap-3 cursor-pointer rounded-2xl p-3" style="background:rgba(244,63,94,.12);border:1px solid rgba(244,63,94,.4)">
        <input type="checkbox" id="rmBlock" class="mt-1 w-5 h-5 accent-rose-500">
        <span><b>Block this name</b><span class="block text-sm text-muted">Use this for offensive names. Nobody can join with this name (or a look-alike spelling) again. You can undo it below the players list.</span></span></label>
      <div class="flex justify-end gap-2"><button class="btn" id="rmCancel">Cancel</button>
        <button class="btn btn-danger !bg-rose-600 !text-white" id="rmGo"><i class="fa-solid fa-user-xmark"></i> Remove player</button></div></div>`;
    document.body.appendChild(m);
    const close = () => m.remove();
    m.addEventListener('click', e => { if (e.target === m) close(); });
    m.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    m.querySelector('#rmCancel').onclick = close;
    m.querySelector('#rmGo').onclick = async () => {
      const block = m.querySelector('#rmBlock').checked;
      const r = await act('player_delete', { id, block: block ? '1' : '0' }, block ? `${p.name} removed and blocked` : `${p.name} removed`);
      if (r.ok) close();
    };
    setTimeout(() => m.querySelector('#rmCancel').focus(), 50);
  }
  const unblockName = key => act('unblock_name', { key }, 'Name allowed again');
  const deleteDraw = id => { if (confirm('Remove this prize from the log? That person becomes eligible to win again.')) act('delete_draw', { id }, 'Removed'); };
  const resetScores = () => { if (confirm('Reset ALL scores and prize history for this event? Players stay joined.')) act('reset_scores', {}, 'Scores reset'); };
  const resetGame = () => RQ.resetGameDialog({ call, data, onDone: r => { if (r && r.questions !== undefined) applyData(r); else loadData(); } });
  function exportCsv(action) {
    if (!data.plan.export) { RQ.toast('Downloading results is part of the Pro plan.', 'error'); return; } window.location = `api.php?action=${action}&csrf=${encodeURIComponent(window.RQ_CSRF)}`; }
  async function logout() { await RQ.api('logout'); location.reload(); }


  /* ------------------------------------------------------------ plan */
  function renderPlan() {
    const p = data.plan;
    const q = p.quizzes ? `${p.quizzesUsed} of ${p.quizzes} quizzes used this month` : 'Unlimited quizzes';
    const pl = p.players ? `Up to ${RQ.fmt(p.players)} players per event (${p.playersInEvent} now)` : 'Unlimited players';
    const feats = [['branding', 'Branding'], ['ppt', 'PowerPoint'], ['export', 'Excel downloads'], ['team', 'Organization users']]
      .map(([k, l]) => `<span class="${p[k] ? 'text-emerald-300' : 'text-[color:var(--dim)] line-through'}">${p[k] ? '✓' : '✗'} ${l}</span>`).join(' · ');
    $('planCard').innerHTML = `<p class="flex items-center justify-between"><span class="font-display font-bold text-lg">${p.label} plan</span>
      ${data.orgMember ? `<span class="chip chip-cyan !text-[10px]">🏛️ ${RQ.esc(data.orgName)}</span>` : ''}</p>
      <p class="text-muted">${q}</p><p class="text-muted">${pl}</p><p class="text-xs leading-relaxed">${feats}</p>
      ${data.me.planUntil && !data.orgMember ? `<p class="text-xs ${data.me.planExpired ? 'text-rose-300' : 'text-muted'}">${data.me.planExpired ? 'Your paid plan ended' : 'Paid through'} ${new Date(data.me.planUntil).toLocaleDateString()}</p>` : ''}
      ${(data.openInvoices || []).map(i => `<a href="${RQ.esc(i.url)}" target="_blank" class="block text-xs font-bold text-amber-200 hover:underline">🧾 Invoice ${RQ.esc(i.number)} (${RQ.esc(i.amount)}) — waiting for payment</a>`).join('')}
      ${data.canUpgrade && p.key !== 'enterprise' ? `<a href="signup.php" class="btn btn-sm btn-gold w-full !justify-center mt-1"><i class="fa-solid fa-arrow-up"></i> ${p.key === 'pro' ? 'Renew or see plans' : 'Upgrade plan'}</a>` : ''}`;
  }

  /* ------------------------------------------------------------ branding (Pro) */
  const PALETTES = [['RizQuiz', '#ff4fd8', '#8b5cf6', '#0b0820'], ['Ocean', '#22d3ee', '#3b82f6', '#06142e'], ['Forest', '#a3e635', '#10b981', '#06180f'],
    ['Sunset', '#fb923c', '#f43f5e', '#1f0a14'], ['Royal', '#fbbf24', '#6366f1', '#0b0b2a'], ['Crimson', '#f87171', '#b91c1c', '#140606'], ['Mono', '#e5e7eb', '#94a3b8', '#0f1115']];
  function renderBrandEditor() {
    const can = data.plan.branding && !data.orgMember;
    $('brandEdit').classList.toggle('hidden', !can);
    $('brandLocked').classList.toggle('hidden', can);
    if (!data.plan.branding) $('brandLocked').innerHTML = '🔒 <b>Branding is part of the Pro plan.</b> Add your own logo and colors to every screen. <a href="signup.php" class="underline font-bold">Upgrade to Pro</a>.';
    else if (data.orgMember) $('brandLocked').innerHTML = `🏛️ <b>Your organization's official branding is applied.</b> It's managed by your organization's master user (${RQ.esc(data.orgName)}).`;
    if (!can) return;
    const c = data.config;
    $('bPrimary').value = c.brandPrimary || '#ff4fd8'; $('bAccent').value = c.brandAccent || '#8b5cf6'; $('bBg').value = c.brandBg || '#0b0820';
    $('logoPreview').innerHTML = c.brandLogo ? `<img src="${RQ.esc(c.brandLogo)}" class="w-full h-full object-contain">` : '⚡';
    $('palettes').innerHTML = PALETTES.map(([n, a, b, bg]) => `<button type="button" class="chip !py-1.5" data-p="${a},${b},${bg}">
      <span class="w-4 h-4 rounded-full" style="background:${a}"></span><span class="w-4 h-4 rounded-full -ml-2" style="background:${b}"></span><span class="w-4 h-4 rounded-full -ml-2 border border-white/30" style="background:${bg}"></span> ${n}</button>`).join('');
    $('palettes').querySelectorAll('button').forEach(b => b.onclick = () => { const [a, c2, bg] = b.dataset.p.split(','); $('bPrimary').value = a; $('bAccent').value = c2; $('bBg').value = bg; previewBrand(); });
  }
  function previewBrand() { RQ.applyBrand({ primary: $('bPrimary').value, accent: $('bAccent').value, bg: $('bBg').value, logo: data.config.brandLogo || null }); }
  async function saveBrand() {
    const r = await call('save_config', { config: { brandPrimary: $('bPrimary').value, brandAccent: $('bAccent').value, brandBg: $('bBg').value } });
    if (r.ok) { RQ.toast('Branding saved — it now shows on every screen', 'ok'); loadData(); }
  }
  async function resetBrand() {
    const r = await call('save_config', { config: { brandPrimary: '', brandAccent: '', brandBg: '' } });
    if (r.ok) { RQ.toast('Back to RizQuiz colors', 'ok'); loadData(); }
  }
  async function uploadLogo(input) {
    const f = input.files[0]; input.value = ''; if (!f) return;
    const fd = new FormData(); fd.append('logo', f);
    const res = await fetch('api.php?action=brand_logo', { method: 'POST', body: fd, headers: { 'X-CSRF': window.RQ_CSRF } });
    const r = await res.json().catch(() => ({ ok: false, error: 'Upload failed.' }));
    if (r.ok) { RQ.toast('Logo uploaded', 'ok'); loadData(); } else RQ.toast(r.error, 'error');
  }
  async function removeLogo() { const r = await call('brand_logo_remove'); if (r.ok) { RQ.toast('Logo removed', 'ok'); loadData(); } }

  /* ------------------------------------------------------------ music */
  function fillSounds() {
    const opt = (list, cur, chosen) => `<option value="">Automatic (${RQ.esc(chosen)})</option>` + list.map(f => `<option value="${RQ.esc(f)}" ${f === cur ? 'selected' : ''}>${RQ.esc(f)}</option>`).join('');
    $('cQSound').innerHTML = opt(data.sounds.question, data.config.questionSound, data.soundsChosen.question);
    $('cPSound').innerHTML = opt(data.sounds.podium, data.config.podiumSound, data.soundsChosen.podium);
  }
  let previewAudio = null;
  function previewSound(id) {
    if (previewAudio) { previewAudio.pause(); previewAudio = null; return; }
    const f = $(id).value || (id === 'cQSound' ? data.soundsChosen.question : data.soundsChosen.podium);
    previewAudio = new Audio(f); previewAudio.play().catch(() => RQ.toast('Could not play ' + f, 'error'));
    setTimeout(() => { if (previewAudio) { previewAudio.pause(); previewAudio = null; } }, 8000);
  }

  /* ------------------------------------------------------------ organization users (Enterprise) */
  let team = [];
  async function teamLoad() {
    const r = await call('team_list', {}, true); if (!r.ok) return;
    team = r.team;
    $('teamList').innerHTML = team.length ? team.map(t => `<div class="glass-2 p-3 space-y-2">
        <div class="flex items-center justify-between gap-2"><p class="font-display font-bold">${RQ.esc(t.name)}</p>
          <span class="chip ${t.status === 'paused' ? 'chip-red' : 'chip-green'} !text-[10px]">${t.status === 'paused' ? 'Paused' : 'Active'}</span></div>
        <p class="text-xs text-muted">${RQ.esc(t.username)} · QR link …/${RQ.esc(t.joinPath)} · ${t.twoFactor ? '2-step ✓' : '2-step on first sign-in'}</p>
        <p class="text-xs text-muted">${t.events} events · ${t.banks} banks · ${t.players} players · last sign-in ${t.lastLogin ? new Date(t.lastLogin).toLocaleDateString() : '—'}</p>
        <div class="flex flex-wrap gap-1.5">
          <button class="btn btn-sm" onclick="A.teamAct('team_update',${t.id},{status:'${t.status === 'paused' ? 'active' : 'paused'}'})">${t.status === 'paused' ? 'Resume' : 'Pause'}</button>
          <button class="btn btn-sm" onclick="A.teamPw(${t.id})"><i class="fa-solid fa-key"></i></button>
          <button class="btn btn-sm" onclick="A.teamAct('team_reset_2fa',${t.id},{},'Reset their two-step sign-in?')"><i class="fa-solid fa-shield-halved"></i></button>
          <button class="btn btn-sm btn-danger" onclick="A.teamAct('team_remove',${t.id},{},'Delete this user and all of their quizzes and results?')"><i class="fa-solid fa-trash"></i></button></div></div>`).join('')
      : '<p class="text-muted">No users yet.</p>';
    if (!$('tPw').value) $('tPw').value = Math.random().toString(36).slice(2, 8) + Math.floor(Math.random() * 90 + 10) + 'Rq';
  }
  async function teamAdd() {
    const r = await call('team_add', { display_name: $('tName').value, username: $('tUser').value, handle: $('tHandle').value, password: $('tPw').value });
    if (!r.ok) return;
    const msg = `Your RizQuiz account is ready.\nSign in: ${new URL('login.php', location.href).href}\nUsername: ${r.admin.username}\nTemporary password: ${$('tPw').value}\nYour player link: ${new URL(r.admin.joinPath, location.href).href}`;
    try { navigator.clipboard.writeText(msg).catch(() => {}); } catch (e) {}
    alert('User added! Sign-in details (copied to your clipboard):\n\n' + msg);
    ['tName', 'tUser', 'tHandle', 'tPw'].forEach(id => $(id).value = '');
    teamLoad();
  }
  async function teamAct(action, id, extra = {}, ask = null) {
    if (ask && !confirm(ask)) return;
    const r = await call(action, Object.assign({ id }, extra)); if (r.ok) { RQ.toast('Done', 'ok'); teamLoad(); }
  }
  function teamPw(id) { const pw = prompt('New temporary password (10+ characters, letters and numbers). They\'ll be asked to change it:'); if (pw) teamAct('team_reset_password', id, { password: pw }); }

  /* ------------------------------------------------------------ Excel (Pro) */
  async function downloadExcel() {
    if (!data.plan.export) { RQ.toast('Excel downloads are part of the Pro plan.', 'error'); return; }
    await RQ.excelBackup(call);
  }

  /* ------------------------------------------------------------ PowerPoint (Pro) */
  const hex = c => (c || '').replace('#', '').toUpperCase();
  async function toDataUrl(src) {
    try { const b = await (await fetch(src)).blob(); return await new Promise(res => { const fr = new FileReader(); fr.onload = () => res(fr.result); fr.readAsDataURL(b); }); }
    catch (e) { return null; }
  }
  function qrDataUrl(text) {
    const box = document.createElement('div'); new QRCode(box, { text, width: 600, height: 600, correctLevel: QRCode.CorrectLevel.M });
    const c = box.querySelector('canvas'); return c ? c.toDataURL('image/png') : null;
  }
  async function downloadPpt() {
    if (!data.plan.ppt) { RQ.toast('PowerPoint is part of the Pro plan.', 'error'); return; }
    if (typeof PptxGenJS === 'undefined') { RQ.toast('The PowerPoint tool is still loading — try again in a second.', 'error'); return; }
    if (!qs.length) { RQ.toast('Add some questions first.', 'error'); return; }
    const b = data.brand || {};
    const BG = hex(b.bg) || '0B0820', P1 = hex(b.primary) || 'FF4FD8', P2 = hex(b.accent) || '8B5CF6';
    const AC = ['F43F5E', '3B82F6', 'F59E0B', '10B981', 'A855F7', '06B6D4'];
    const FONT = 'Segoe UI', HEAD = 'Segoe UI Black';
    const liveUrl = new URL('live.php', location.href).href;
    const logo = b.logo ? await toDataUrl(b.logo) : null;
    const pptx = new PptxGenJS();
    pptx.layout = 'LAYOUT_WIDE'; pptx.title = `${data.config.title} — ${data.bank.name}`;
    const frame = (s) => { s.background = { color: BG }; s.addShape(pptx.ShapeType.rect, { x: 0, y: 7.3, w: 13.33, h: .2, fill: { color: P1 } });
      if (logo) s.addImage({ data: logo, x: 12.2, y: .25, w: .85, h: .85, sizing: { type: 'contain', w: .85, h: .85 } }); };
    // title slide with join QR
    let s = pptx.addSlide(); frame(s);
    s.addText(data.config.title, { x: .6, y: .9, w: 7.4, h: 1.4, fontFace: HEAD, fontSize: 54, bold: true, color: 'FFFFFF' });
    s.addText(`${data.bank.name} · ${data.event.name}`, { x: .6, y: 2.3, w: 7.4, h: .6, fontFace: FONT, fontSize: 22, color: 'C9CFF0' });
    s.addText([{ text: '1  Scan the QR code\n', options: { breakLine: true } }, { text: '2  Pick a name and an emoji\n', options: { breakLine: true } }, { text: '3  Answer fast — speed = points!' }],
      { x: .6, y: 3.2, w: 7.4, h: 2, fontFace: FONT, fontSize: 24, color: 'FFFFFF', paraSpaceAfter: 8 });
    s.addText(`Game code ${data.gameCode}  ·  ${data.joinUrl.replace(/^https?:\/\//, '')}`, { x: .6, y: 5.6, w: 7.6, h: .6, fontFace: FONT, fontSize: 18, color: P1, bold: true });
    const qr = qrDataUrl(data.joinUrl);
    if (qr) { s.addShape(pptx.ShapeType.roundRect, { x: 8.55, y: 1.05, w: 4.1, h: 4.1, fill: { color: 'FFFFFF' }, rectRadius: .2 }); s.addImage({ data: qr, x: 8.75, y: 1.25, w: 3.7, h: 3.7 }); }
    s.addText('▶ Open the live game', { x: 8.55, y: 5.4, w: 4.1, h: .5, fontFace: FONT, fontSize: 16, color: 'FFFFFF', align: 'center', hyperlink: { url: liveUrl } });

    qs.forEach((q, i) => {
      const t = q.type || 'mc', info = RQT.INFO[t];
      s = pptx.addSlide(); frame(s);
      s.addText(`QUESTION ${i + 1} OF ${qs.length}  ·  ${info.icon} ${info.label.toUpperCase()}`, { x: .6, y: .35, w: 10, h: .5, fontFace: FONT, fontSize: 14, bold: true, color: P1, charSpacing: 2 });
      s.addText(q.text, { x: .6, y: .9, w: 12.1, h: 1.7, fontFace: HEAD, fontSize: 34, bold: true, color: 'FFFFFF', valign: 'middle' });
      const opts = q.options || [];
      if (['mc', 'tf', 'poll'].includes(t)) {
        const cols = opts.length <= 4 ? 2 : 3, rows = Math.ceil(opts.length / cols), w = (12.1 - (cols - 1) * .25) / cols, h = Math.min(1.9, (4.2 - (rows - 1) * .25) / rows);
        opts.forEach((o, k) => s.addText(`${String.fromCharCode(65 + k)}   ${o}`, { x: .6 + (k % cols) * (w + .25), y: 2.85 + Math.floor(k / cols) * (h + .25), w, h,
          shape: pptx.ShapeType.roundRect, rectRadius: .15, fill: { color: AC[k % 6] }, fontFace: FONT, fontSize: 24, bold: true, color: 'FFFFFF', margin: 14, valign: 'middle' }));
      } else {
        const msg = { typeans: '⌨️  Type your answer on your phone', estimate: `🎯  Guess a number from ${q.min} to ${q.max}${q.unit ? ' ' + q.unit : ''}`,
          ranking: '🔢  Put these in the right order:\n' + (q.items || []).slice().sort().map(x => '•  ' + x).join('\n'),
          rating: `⭐  Rate it from 1 (${q.lowLabel}) to ${q.scale} (${q.highLabel})`, wordcloud: '☁️  Send a word or two from your phone',
          ideas: '💡  Share your ideas on your phone — then vote for the best one!' }[t];
        s.addText(msg, { x: .6, y: 2.9, w: 12.1, h: 3.9, fontFace: FONT, fontSize: 28, color: 'FFFFFF', valign: 'top', shape: pptx.ShapeType.roundRect, rectRadius: .15, fill: { color: P2, transparency: 55 }, margin: 20 });
      }
      s.addText('▶ Run this question live', { x: 9.6, y: 6.75, w: 3.2, h: .45, fontFace: FONT, fontSize: 13, color: 'C9CFF0', align: 'right',
        hyperlink: { url: `${liveUrl}?run=${i}&bank=${data.bank.id}` } });
      s.addNotes(`Answer: ${summaryText(q)}${q.note ? '\nComment: ' + q.note : ''}`);
      if (QTYPE_SCORED[t]) {                              // answer slide
        s = pptx.addSlide(); frame(s);
        s.addText(`ANSWER  ·  QUESTION ${i + 1}`, { x: .6, y: .35, w: 10, h: .5, fontFace: FONT, fontSize: 14, bold: true, color: '34D399', charSpacing: 2 });
        s.addText(q.text, { x: .6, y: .9, w: 12.1, h: 1.3, fontFace: FONT, fontSize: 24, color: 'C9CFF0', valign: 'middle' });
        s.addText('✅  ' + summaryText(q), { x: .6, y: 2.5, w: 12.1, h: 2, fontFace: HEAD, fontSize: 40, bold: true, color: 'FFFFFF', shape: pptx.ShapeType.roundRect, rectRadius: .2, fill: { color: '059669' }, margin: 20, valign: 'middle' });
        if (q.note) s.addText('💬  ' + q.note, { x: .6, y: 4.8, w: 12.1, h: 1.6, fontFace: FONT, fontSize: 22, color: 'E0F7FF', valign: 'top' });
      }
    });
    s = pptx.addSlide(); frame(s);
    s.addText('🏆 Grand Podium', { x: .6, y: 2.2, w: 12.1, h: 1.6, fontFace: HEAD, fontSize: 60, bold: true, color: 'FFFFFF', align: 'center' });
    s.addText('▶ Reveal the champions live', { x: .6, y: 4, w: 12.1, h: .7, fontFace: FONT, fontSize: 22, color: P1, align: 'center', hyperlink: { url: `${liveUrl}?show=PODIUM` } });
    await pptx.writeFile({ fileName: `${data.bank.name.replace(/[^\w\- ]+/g, '').trim().replace(/\s+/g, '_') || 'RizQuiz'}.pptx` });
    RQ.toast('PowerPoint downloaded', 'ok');
  }
  function summaryText(q) {
    const t = q.type || 'mc';
    if (t === 'mc' || t === 'tf') return q.options[q.correctIndex];
    if (t === 'typeans') return (q.accepted || []).join(' / ');
    if (t === 'estimate') return `${q.answer}${q.unit ? ' ' + q.unit : ''}${q.tolerance ? ' (±' + q.tolerance + ')' : ''}`;
    if (t === 'ranking') return (q.items || []).join(' → ');
    return '(opinion question — results appear live)';
  }

  /* ------------------------------------------------------------ init */
  window.addEventListener('hashchange', route);
  document.addEventListener('DOMContentLoaded', () => { loadData(); setInterval(() => { if (location.hash === '#players') loadData(); }, 5000); });

  return { useBank, appendBank, renameBank, dupBank, deleteBank, newBank, previewBank, renderBanks, currentBankId, renderQuestions,
    saveQuestion, resetForm, prizeToggle, editQ, dupQ, delQ, moveQ, applyAllTime, clearQuestions, openJson, closeJson, applyJson, loadFile, exportJson,
    modePicked, saveSettings, renameEvent, renameSession, newSession, newEvent, switchEvent, renamePlayer, deletePlayer, deleteDraw,
    resetScores, resetGame, unblockName, exportCsv, renderPlayers, makeShort, previewBrand, saveBrand, resetBrand, uploadLogo, removeLogo, previewSound,
    teamAdd, teamAct, teamPw, downloadExcel, downloadPpt, launch, closeGame, changePassword, newBackupCodes };
})();
