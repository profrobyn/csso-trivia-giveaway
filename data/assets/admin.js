/* RizQuiz host/admin logic. */
window.A = (function () {
  const $ = id => document.getElementById(id);
  let data = null;      // admin_data
  let live = null;      // latest stage data
  let qs = [];          // question bank
  let lastLiveQKey = null;
  let jumpBuiltFor = null;
  let busy = false;

  const PHASE = { LOBBY: 'Lobby', QUESTION: 'Question Live', REVEAL: 'Answer Revealed', WINNER: 'Prize Winner', LEADERBOARD: 'Leaderboard', PODIUM: 'Grand Podium' };

  async function call(action, payload = {}, quiet = false) {
    const r = await RQ.api(action, payload);
    if (!r.ok) {
      if (r.code === 'AUTH') { RQ.toast(r.error, 'error'); setTimeout(() => location.reload(), 1200); }
      else if (!quiet) RQ.toast(r.error, 'error');
    }
    return r;
  }

  /* ------------------------------------------------------------ tabs */
  function setTab(t) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.toggle('hidden', p.id !== 'tab-' + t));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === t));
    try { localStorage.setItem('rq_admin_tab', t); } catch (e) {}
    if (t === 'event') loadData();
  }

  /* ------------------------------------------------------------ data */
  async function loadData() {
    const r = await call('admin_data', {}, true);
    if (!r.ok) return;
    applyData(r);
  }
  function applyData(r) {
    data = r;
    qs = r.questions || [];
    $('hdrTitle').textContent = r.config.title;
    $('hdrEvent').textContent = `${r.event.name} · ${r.session.label}`;
    document.title = r.config.title + ' — Host';
    renderWarnings(); renderQuestions(); renderBanks(); fillSettings(); renderEvent(); renderPlayers(); renderWinLog();
    $('quickAuto').checked = !!r.config.prizeEveryQuestion;
    jumpBuiltFor = null;
    if (live) updateLive(live);
  }

  function renderWarnings() {
    const w = [];
    if (data.defaultPassword) w.push(`<b>Security:</b> you're still using the default admin password. Change <code>ADMIN_PASSWORD</code> in <code>config.php</code> before your event.`);
    const url = data.config.joinUrl || data.autoJoinUrl;
    if (!data.config.joinUrl && data.autoJoinIsLocal) w.push(`<b>Phones can't open “${RQ.esc(url)}”.</b> Open this admin page using your computer's network address (e.g. http://192.168.x.x/RizQuiz/admin.php) or set a Join URL in Prizes &amp; Settings.`);
    if (!qs.length) w.push(`<b>No questions loaded yet.</b> Add some in the Questions tab or load a JSON file.`);
    $('warnings').innerHTML = w.map(x => `<div class="px-4 py-2.5 rounded-xl bg-amber-950/70 border border-amber-600 text-amber-100 text-xs"><i class="fa-solid fa-triangle-exclamation text-amber-400 mr-1"></i> ${x}</div>`).join('');
  }

  /* ------------------------------------------------------------ live */
  function updateLive(d) {
    live = d;
    if (!data) return;
    const total = qs.length;
    $('stPhase').textContent = PHASE[d.phase] || d.phase;
    const pos = d.adhoc ? d.mainIndex : d.qIndex;   // place in the loaded bank (bonus questions don't move it)
    const nm = d.qName ? ` · “${d.qName}”` : '';
    $('stQ').textContent = d.adhoc ? `Bonus question${nm}` : (d.qIndex >= 0 ? `Question ${d.qIndex + 1} of ${total}${nm}` : `${total} questions ready`);
    $('stPlayers').textContent = `${d.counts.active} here now · ${d.counts.registered} in event`;
    $('stDay').textContent = `${d.event.name} · ${d.session.label}`;

    const next = pos + 1;
    const btn = $('btnNext');
    if (!total) { btn.innerHTML = '<i class="fa-solid fa-plus"></i> Add questions first'; btn.disabled = true; }
    else if (pos < 0) { btn.innerHTML = '<i class="fa-solid fa-play"></i> Start Question 1'; btn.disabled = false; }
    else if (next < total) { btn.innerHTML = `Next Question (${next + 1}/${total}) <i class="fa-solid fa-arrow-right"></i>`; btn.disabled = d.phase === 'QUESTION'; }
    else { btn.innerHTML = '<i class="fa-solid fa-trophy"></i> Show Grand Podium'; btn.disabled = d.phase === 'QUESTION' || d.phase === 'PODIUM'; }
    $('btnEnd').disabled = d.phase !== 'QUESTION';
    $('btnWinner').disabled = d.phase === 'QUESTION';

    const k = d.session.id + ':' + d.qIndex + ':' + d.startedAt;
    if (k !== lastLiveQKey) {
      lastLiveQKey = k;
      const def = d.prizeMode && d.prizeMode !== 'none' ? d.prizeMode
        : (data.config.prizeMode === 'mix' ? 'random_correct' : data.config.prizeMode);
      $('drawMode').value = def;
    }
    let note;
    if (!d.prizeEveryQuestion) note = '🎁 Auto prizes are OFF — click Winner! whenever you want to award a prize.';
    else if (d.prizeMode === 'none') note = '🚫 This question is set to no prize (you can still click Winner!).';
    else if (d.prizeMode) note = `🎁 Auto prize this round: ${RQ.MODE[d.prizeMode].label}, drawn ${d.autoDrawDelay}s after the reveal. Click Winner! for extra prizes.`;
    else note = `🎁 Auto prizes are ON (${RQ.MODE[data.config.prizeMode].label}).`;
    $('autoNote').textContent = note;

    $('roundWinners').innerHTML = d.roundDraws.length
      ? `<span class="text-[11px] text-slate-400 font-bold self-center">This round:</span>` + d.roundDraws.map(w =>
        `<span class="px-2.5 py-1 rounded-full text-xs font-bold ${w.name ? 'bg-emerald-950 text-emerald-200 border border-emerald-600' : 'bg-slate-800 text-slate-400'}">
          ${w.name ? RQ.esc(w.emoji) + ' ' + RQ.esc(w.name) : 'No eligible winner'} · ${RQ.MODE[w.mode].short}${w.auto ? ' (auto)' : ''}</span>`).join('')
      : '';

    if (jumpBuiltFor !== qs.length + ':' + pos + ':' + qs.map(q => q.name || '').join('|')) {
      jumpBuiltFor = qs.length + ':' + pos + ':' + qs.map(q => q.name || '').join('|');
      $('jumpSel').innerHTML = qs.map((q, i) => `<option value="${i}">Q${i + 1}: ${q.name ? '[' + RQ.esc(q.name) + '] ' : ''}${RQ.esc(q.text.length > 32 ? q.text.slice(0, 32) + '…' : q.text)}</option>`).join('');
      $('jumpSel').value = Math.min(Math.max(0, next), Math.max(0, qs.length - 1));
    }
  }

  async function start(i, bankId = null) {
    if (busy) return; busy = true;
    RQ.audio && RQ.audio.stop();
    const r = await call('start_question', bankId ? { index: i, bankId } : { index: i });
    busy = false;
    if (r.ok) RQStage.refreshNow();
  }
  function next() {
    if (!live || !qs.length) return;
    if (live.phase === 'QUESTION') { RQ.toast('A question is still running.', 'error'); return; }
    const n = (live.adhoc ? live.mainIndex : live.qIndex) + 1;
    if (n < qs.length) start(n); else phase('PODIUM');
  }
  function jump() {
    const i = parseInt($('jumpSel').value, 10);
    if (isNaN(i)) return;
    if (live && live.phase === 'QUESTION' && !confirm('A question is running. Stop it and jump?')) return;
    if (live && !live.adhoc && i <= live.qIndex && !confirm(`Replay Question ${i + 1}? Any answers already given to it today will be cleared and re-scored.`)) return;
    start(i);
  }
  /* ---------- pull up a question by name (from any bank) ---------- */
  let pullTimer = null;
  function pullSearch() {
    clearTimeout(pullTimer);
    pullTimer = setTimeout(async () => {
      const term = $('pullInput').value.trim();
      const r = await call('question_search', { q: term }, true);
      if (!r.ok) return;
      const box = $('pullResults');
      box.classList.remove('hidden');
      box.innerHTML = r.hits.length ? (term ? '' : '<p class="text-[10px] uppercase tracking-wider font-black text-slate-400 px-2 pt-1">Named questions</p>') + r.hits.map(h => `
        <div class="flex items-center gap-2 p-2 rounded-lg hover:bg-cssoNavyLight">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-black text-white truncate">${h.name ? `<span class="text-sky-300">${RQ.esc(h.name)}</span>` : `<span class="text-slate-500">(no name)</span>`}
              <span class="text-[10px] font-bold ${h.loaded ? 'text-cssoGold' : 'text-slate-400'}"> · ${h.loaded ? 'Q' + (h.index + 1) + ' in loaded bank' : RQ.esc(h.bankName) + ' Q' + (h.index + 1) + ' · runs as bonus'}</span></p>
            <p class="text-[11px] text-slate-400 truncate">${RQ.esc(h.text)}</p>
          </div>
          <button class="btn btn-green !py-1 !px-2.5 shrink-0" onclick="A.pullRun(${h.bankId}, ${h.index}, ${h.loaded})"><i class="fa-solid fa-play"></i> Run</button>
        </div>`).join('')
        : `<p class="text-xs text-slate-400 italic p-2">${term ? 'No questions match “' + RQ.esc(term) + '”.' : 'No named questions yet. Give questions a name in the Questions tab.'}</p>`;
    }, 150);
  }
  function pullRun(bankId, index, loaded) {
    $('pullResults').classList.add('hidden'); $('pullInput').value = '';
    if (live && live.phase === 'QUESTION' && !confirm('A question is running. Stop it and run this one?')) return;
    start(index, loaded ? null : bankId);
  }
  document.addEventListener('click', e => {
    const box = $('pullResults');
    if (box && !box.contains(e.target) && e.target.id !== 'pullInput') box.classList.add('hidden');
  });

  async function endQ() { const r = await call('end_question'); if (r.ok) RQStage.refreshNow(); }
  async function draw() {
    if (busy) return; busy = true;
    const r = await call('draw', { mode: $('drawMode').value });
    busy = false;
    if (r.ok) { RQStage.refreshNow(); if (!r.draw.name) RQ.toast('No eligible player for that prize type — try another type.', 'info'); }
  }
  async function phase(p) {
    if (p === 'PODIUM' && live && live.phase === 'QUESTION' && !confirm('A question is still running. End it and show the podium?')) return;
    const r = await call('set_phase', { phase: p });
    if (r.ok) RQStage.refreshNow();
  }
  async function quickAuto(on) {
    const r = await call('save_config', { config: { prizeEveryQuestion: on } });
    if (r.ok) { data.config = r.config; RQ.toast(on ? 'Auto prize after every question: ON' : 'Auto prizes OFF — use the Winner! button', 'ok'); RQStage.refreshNow(); }
  }
  function fullscreen() {
    const el = $('stageRoot');
    if (document.fullscreenElement) document.exitFullscreen(); else if (el.requestFullscreen) el.requestFullscreen();
  }

  /* ------------------------------------------------------- questions */
  function renderQuestions() {
    $('qCount').textContent = qs.length;
    const term = (($('qSearch') && $('qSearch').value) || '').toLowerCase().trim();
    const shown = qs.filter(q => !term || (q.name || '').toLowerCase().includes(term) || q.text.toLowerCase().includes(term)).length;
    if (qs.length && !shown) { $('qList').innerHTML = `<p class="text-xs text-slate-400 italic py-4 text-center">No questions match “${RQ.esc(term)}”.</p>`; return; }
    $('qList').innerHTML = qs.length ? qs.map((q, i) => {
      if (term && !((q.name || '').toLowerCase().includes(term) || q.text.toLowerCase().includes(term))) return '';
      const pm = q.prizeMode && q.prizeMode !== 'default' ? `<span class="px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-700">${RQ.MODE[q.prizeMode].short}</span>` : '';
      return `<div class="bg-cssoNavyDark p-3 rounded-xl border border-cssoNavyLight flex items-center justify-between gap-2 text-xs">
        <div class="min-w-0 flex-1 space-y-0.5">
          <p class="font-bold text-white truncate"><span class="text-cssoGold mr-1">#${i + 1}</span>${q.name ? `<span class="px-1.5 py-0.5 mr-1 rounded bg-sky-950 text-sky-300 border border-sky-700 text-[10px] font-black">${RQ.esc(q.name)}</span>` : ''}${RQ.esc(q.text)}</p>
          <p class="text-[10px] text-slate-400 flex flex-wrap gap-2 items-center"><span>✅ ${RQ.letter(q.correctIndex)}: ${RQ.esc(q.options[q.correctIndex])}</span><span>⏱ ${q.timeLimit}s</span><span>${q.options.length} options</span>${pm}</p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <button onclick="A.moveQ(${i},-1)" class="p-1.5 ${i === 0 ? 'opacity-20' : 'text-slate-300 hover:text-cssoGold'}" ${i === 0 ? 'disabled' : ''}><i class="fa-solid fa-arrow-up"></i></button>
          <button onclick="A.moveQ(${i},1)" class="p-1.5 ${i === qs.length - 1 ? 'opacity-20' : 'text-slate-300 hover:text-cssoGold'}" ${i === qs.length - 1 ? 'disabled' : ''}><i class="fa-solid fa-arrow-down"></i></button>
          <button onclick="A.editQ(${i})" class="p-1.5 text-emerald-400" title="Edit"><i class="fa-solid fa-pen"></i></button>
          <button onclick="A.dupQ(${i})" class="p-1.5 text-sky-400" title="Duplicate"><i class="fa-solid fa-clone"></i></button>
          <button onclick="A.delQ(${i})" class="p-1.5 text-rose-400" title="Delete"><i class="fa-solid fa-trash"></i></button>
        </div></div>`;
    }).join('') : `<p class="text-xs text-slate-400 italic py-4 text-center">No questions yet. Add one above or load a JSON file.</p>`;
  }

  async function saveQs(msg) {
    const r = await call('save_questions', { questions: qs });
    if (r.ok) { qs = r.questions; data.questions = qs; data.banks = r.banks; renderQuestions(); renderBanks(); renderWarnings(); jumpBuiltFor = null; if (live) updateLive(live); if (msg) RQ.toast(msg, 'ok'); }
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
    // live-tab switcher
    $('liveBankSel').innerHTML = data.banks.map(b => `<option value="${b.id}" ${b.active ? 'selected' : ''}>${RQ.esc(b.name)} (${b.count})</option>`).join('');
    const s = ($('bankSearch').value || '').toLowerCase();
    const list = data.banks.filter(b => !s || b.name.toLowerCase().includes(s) || b.preview.some(t => t.toLowerCase().includes(s)));
    $('bankList').innerHTML = list.length ? list.map(b => `
      <div class="rounded-xl border ${b.active ? 'border-cssoGold bg-cssoNavyLight' : 'border-cssoNavyLight bg-cssoNavyDark'} p-3 space-y-2">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-sm font-black text-white truncate">${b.active ? '<i class="fa-solid fa-circle-check text-cssoGold"></i> ' : ''}${RQ.esc(b.name)}</p>
            <p class="text-[11px] text-slate-400">${b.count} question${b.count === 1 ? '' : 's'} · updated ${new Date(b.updated).toLocaleDateString()}</p>
          </div>
          ${b.active ? '<span class="text-[10px] font-black text-cssoGold shrink-0">LOADED</span>'
            : `<button class="btn btn-gold !py-1 !px-3 shrink-0" onclick="A.useBank(${b.id})"><i class="fa-solid fa-upload"></i> Load</button>`}
        </div>
        ${b.preview.length ? `<p class="text-[11px] text-slate-400 truncate">${b.preview.map(t => RQ.esc(t)).join(' · ')}</p>` : ''}
        <div class="flex flex-wrap gap-1.5">
          <button class="btn btn-dark !py-0.5 !px-2 !text-[11px]" onclick="A.previewBank(${b.id})"><i class="fa-solid fa-eye"></i> ${openPreview === b.id ? 'Hide' : 'View'}</button>
          ${b.active ? '' : `<button class="btn btn-dark !py-0.5 !px-2 !text-[11px]" onclick="A.appendBank(${b.id})" title="Copy this bank's questions onto the end of the loaded bank"><i class="fa-solid fa-plus"></i> Add to Loaded</button>`}
          <button class="btn btn-dark !py-0.5 !px-2 !text-[11px]" onclick="A.renameBank(${b.id})"><i class="fa-solid fa-pen"></i></button>
          <button class="btn btn-dark !py-0.5 !px-2 !text-[11px]" onclick="A.dupBank(${b.id})" title="Duplicate"><i class="fa-solid fa-clone"></i></button>
          ${b.active ? '' : `<button class="btn btn-dark !py-0.5 !px-2 !text-[11px] !text-rose-300" onclick="A.deleteBank(${b.id})" title="Delete"><i class="fa-solid fa-trash"></i></button>`}
        </div>
        <div id="bankPrev${b.id}" class="${openPreview === b.id ? '' : 'hidden'} text-[11px] text-slate-300 space-y-1 max-h-48 overflow-y-auto custom-scrollbar"></div>
      </div>`).join('') : `<p class="text-xs text-slate-400 italic p-3">No banks match.</p>`;
    if (openPreview) fillPreview(openPreview);
  }
  async function fillPreview(id) {
    const el = $('bankPrev' + id); if (!el) return;
    const r = await call('bank_view', { id }, true);
    if (!r.ok) return;
    el.innerHTML = r.bank.questions.map((q, i) => `<p><span class="text-cssoGold font-bold">${i + 1}.</span> ${RQ.esc(q.text)} <span class="text-emerald-400">✓ ${RQ.esc(q.options[q.correctIndex])}</span></p>`).join('') || '<p class="italic">Empty bank.</p>';
  }
  function previewBank(id) { openPreview = openPreview === id ? null : id; renderBanks(); }

  async function useBank(id) {
    if (!id || id === currentBankId()) { RQ.toast('That bank is already loaded.', 'info'); return; }
    const b = data.banks.find(x => x.id === id);
    if (live && live.phase === 'QUESTION') { RQ.toast('Wait for the current question to end before switching banks.', 'error'); renderBanks(); return; }
    if (gameInProgress() && !confirm(`Load “${b.name}”? The game will return to the lobby and start again from question 1 of this bank. Scores already earned are kept.`)) { renderBanks(); return; }
    const r = await call('bank_activate', { id });
    if (r.ok) { applyData(r); resetForm(); RQStage.refreshNow(); RQ.toast(`Loaded “${b.name}” (${b.count} questions)`, 'ok'); }
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
    if (r.ok) { applyData(r); resetForm(); RQStage.refreshNow(); RQ.toast('New bank created — add questions below', 'ok'); }
  }

  function resetForm() {
    $('editIdx').value = -1;
    ['fName', 'fText', 'fO0', 'fO1', 'fO2', 'fO3'].forEach(id => $(id).value = '');
    $('fCorrect').value = 0; $('fPrize').value = 'default';
    $('fTime').value = data ? data.config.defaultTime : 15;
    $('formTitle').innerHTML = '<i class="fa-solid fa-plus-circle"></i> Add New Question';
    $('cancelEdit').classList.add('hidden');
  }
  function saveQuestion() {
    const text = $('fText').value.trim();
    const raw = [0, 1, 2, 3].map(i => $('fO' + i).value.trim());
    let ci = parseInt($('fCorrect').value, 10);
    if (!text) { RQ.toast('Please type the question.', 'error'); return; }
    if (!raw[ci]) { RQ.toast('The correct answer option is empty.', 'error'); return; }
    // drop blank options, keeping track of where the correct one lands
    const opts = []; let newCi = 0;
    raw.forEach((o, i) => { if (o) { if (i === ci) newCi = opts.length; opts.push(o); } });
    if (opts.length < 2) { RQ.toast('Please give at least two options.', 'error'); return; }
    const idx = parseInt($('editIdx').value, 10);
    const q = { id: idx >= 0 ? qs[idx].id : 'q_' + Date.now(), name: $('fName').value.trim(), text, options: opts, correctIndex: newCi,
      timeLimit: parseInt($('fTime').value, 10) || 15, prizeMode: $('fPrize').value };
    if (idx >= 0) qs[idx] = q; else qs.push(q);
    saveQs(idx >= 0 ? 'Question updated' : 'Question added').then(ok => ok && resetForm());
  }
  function editQ(i) {
    const q = qs[i];
    $('editIdx').value = i; $('fText').value = q.text; $('fName').value = q.name || '';
    [0, 1, 2, 3].forEach(j => $('fO' + j).value = q.options[j] || '');
    $('fCorrect').value = Math.min(q.correctIndex, 3); $('fTime').value = q.timeLimit; $('fPrize').value = q.prizeMode || 'default';
    $('formTitle').innerHTML = `<i class="fa-solid fa-pen-to-square"></i> Edit Question #${i + 1}`;
    $('cancelEdit').classList.remove('hidden');
    if (q.options.length > 4) RQ.toast('This question has more than 4 options — edit it with Paste / View JSON to keep them all.', 'info');
    $('fText').scrollIntoView({ behavior: 'smooth', block: 'center' });
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
      if (r.ok) { applyData(r); resetForm(); closeJson(); RQStage.refreshNow(); RQ.toast(`Saved “${name}” with ${r.questions.length} questions and loaded it`, 'ok'); }
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
      applyData(r); resetForm(); RQStage.refreshNow();
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
    $('cScope').value = c.leaderboardScope; $('cAutoEnd').checked = c.autoEndWhenAllAnswered; $('cActive').value = c.activeWindowSec;
    $('cJoinUrl').value = c.joinUrl; $('cWifi').checked = c.showWifiWarning; $('cWifiText').value = c.wifiText;
    $('joinHint').innerHTML = `Detected automatically: <span class="text-cssoGold">${RQ.esc(data.autoJoinUrl)}</span>` +
      (data.autoJoinIsLocal ? ' <span class="text-rose-300">(phones can\'t reach localhost)</span>' : '');
    if ($('editIdx').value === '-1' && !$('fText').value) $('fTime').value = c.defaultTime;
    $('modeCards').innerHTML = ['random_correct', 'random_player', 'fastest', 'mix'].map(m => `
      <label class="flex items-start gap-2 p-2.5 rounded-xl border cursor-pointer ${c.prizeMode === m ? 'border-cssoGold bg-cssoNavyLight' : 'border-cssoNavyLight bg-cssoNavyDark'}">
        <input type="radio" name="cMode" value="${m}" ${c.prizeMode === m ? 'checked' : ''} class="mt-1 accent-amber-500" onchange="A.modePicked()">
        <span><span class="text-sm font-bold text-white">${RQ.MODE[m].emoji} ${RQ.MODE[m].label}</span>
        <span class="block text-[11px] text-slate-400">${RQ.esc(RQ.MODE[m].rule)}</span></span></label>`).join('');
    $('mixBox').classList.toggle('hidden', c.prizeMode !== 'mix');
  }
  function modePicked() {
    const m = document.querySelector('input[name=cMode]:checked').value;
    document.querySelectorAll('#modeCards label').forEach(l => {
      const on = l.querySelector('input').value === m;
      l.className = `flex items-start gap-2 p-2.5 rounded-xl border cursor-pointer ${on ? 'border-cssoGold bg-cssoNavyLight' : 'border-cssoNavyLight bg-cssoNavyDark'}`;
    });
    $('mixBox').classList.toggle('hidden', m !== 'mix');
  }
  async function saveSettings() {
    const m = document.querySelector('input[name=cMode]:checked');
    const cfg = {
      prizeEveryQuestion: $('cPrizeEvery').checked, autoDrawDelay: $('cDelay').value, prizeMode: m ? m.value : 'random_correct',
      mixStyle: $('cMixStyle').value, repeatWinners: $('cRepeat').value, announcePrize: $('cAnnounce').checked,
      title: $('cTitle').value, defaultTime: $('cDefTime').value, lockFinalN: $('cLock').value, leaderboardScope: $('cScope').value,
      autoEndWhenAllAnswered: $('cAutoEnd').checked, activeWindowSec: $('cActive').value, joinUrl: $('cJoinUrl').value.trim(),
      showWifiWarning: $('cWifi').checked, wifiText: $('cWifiText').value,
    };
    const r = await call('save_config', { config: cfg });
    if (r.ok) { RQ.toast('Settings saved', 'ok'); loadData(); RQStage.refreshNow(); }
  }

  /* ----------------------------------------------- event & players */
  function renderEvent() {
    $('evName').value = data.event.name;
    $('sessionList').innerHTML = data.sessions.map(s => `
      <span class="px-3 py-1.5 rounded-xl text-xs font-bold border ${s.id == data.session.id ? 'bg-cssoGold text-cssoNavy border-cssoGold' : 'bg-cssoNavyDark text-slate-300 border-cssoNavyLight'}">
        ${RQ.esc(s.label)} <span class="opacity-70 font-semibold">· ${new Date(+s.started_at).toLocaleDateString()} · ${s.played} Qs played</span>
        ${s.id == data.session.id ? ' · CURRENT' : ''}
        <button class="ml-1 opacity-70 hover:opacity-100" onclick="A.renameSession(${s.id}, ${RQ.esc(JSON.stringify(s.label))})" title="Rename"><i class="fa-solid fa-pen text-[10px]"></i></button></span>`).join('');
    $('newSessLabel').placeholder = `e.g. Day ${data.sessions.length + 1}`;
    $('eventList').innerHTML = data.events.map(e => `
      <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-xs ${e.id == data.event.id ? 'bg-cssoNavyLight border border-cssoGold' : 'bg-cssoNavyDark border border-cssoNavyLight'}">
        <span class="min-w-0"><b class="text-white truncate block">${RQ.esc(e.name)}</b><span class="text-slate-400">${e.players} players · ${e.sessions} day(s)</span></span>
        ${e.id == data.event.id ? '<span class="text-cssoGold font-black text-[10px]">CURRENT</span>' : `<button class="btn btn-dark !py-1 !px-2" onclick="A.switchEvent(${e.id})">Switch</button>`}
      </div>`).join('');
  }
  function renderPlayers() {
    if (!data) return;
    const s = ($('pSearch').value || '').toLowerCase();
    const list = data.players.filter(p => !s || p.name.toLowerCase().includes(s));
    $('pCount').textContent = data.players.length;
    $('pOnline').textContent = data.players.filter(p => p.online).length + ' online';
    $('pTable').innerHTML = list.length ? list.map(p => `
      <tr class="border-t border-cssoNavyLight hover:bg-cssoNavyDark">
        <td class="p-2 text-slate-400">${data.players.indexOf(p) + 1}</td>
        <td class="p-2"><span class="inline-block w-2 h-2 rounded-full mr-1 ${p.online ? 'bg-emerald-400' : 'bg-slate-600'}"></span><span class="text-lg align-middle">${RQ.esc(p.emoji)}</span> <b class="text-white">${RQ.esc(p.name)}</b></td>
        <td class="p-2 text-right font-black text-emerald-400">${RQ.fmt(p.total)}</td>
        <td class="p-2 text-right text-slate-300">${RQ.fmt(p.today)}</td>
        <td class="p-2 text-right text-slate-300">${p.correct}</td>
        <td class="p-2 text-right">${p.wins ? '<span class="text-cssoGold font-bold">🎁 ' + p.wins + '</span>' : '<span class="text-slate-600">0</span>'}</td>
        <td class="p-2 font-mono font-bold text-cssoGold tracking-widest">${RQ.esc(p.code)}</td>
        <td class="p-2 text-right whitespace-nowrap">
          <button class="p-1 text-emerald-400" title="Rename" onclick="A.renamePlayer(${p.id})"><i class="fa-solid fa-pen"></i></button>
          <button class="p-1 text-rose-400" title="Remove" onclick="A.deletePlayer(${p.id})"><i class="fa-solid fa-user-xmark"></i></button>
        </td></tr>`).join('') : `<tr><td colspan="8" class="p-4 text-center text-slate-400 italic">No players yet.</td></tr>`;
  }
  function renderWinLog() {
    $('winLog').innerHTML = data.draws.length ? data.draws.map(w => `
      <div class="p-2.5 bg-cssoNavyDark rounded-lg border border-cssoNavyLight flex items-center justify-between gap-2 text-xs">
        <span class="text-white font-semibold">${RQ.esc(w.session || '')} · ${w.qIndex >= 0 ? (w.bank ? RQ.esc(w.bank) + ' ' : '') + 'Q' + (w.qIndex + 1) + (w.qName ? ' “' + RQ.esc(w.qName) + '”' : '') : 'Bonus'} ·
          ${w.name ? RQ.esc(w.emoji) + ' ' + RQ.esc(w.name) : '<span class="text-slate-500">no eligible player' + (w.note === 'all_won' ? ' (all had already won)' : '') + '</span>'}
          ${w.mode === 'fastest' && w.ms !== null ? `<span class="text-slate-400">(${(w.ms / 1000).toFixed(2)}s)</span>` : ''}</span>
        <span class="flex items-center gap-2"><span class="text-[10px] px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-600 font-bold">${RQ.MODE[w.mode].short}${w.auto ? ' · auto' : ''}</span>
        <button class="text-slate-500 hover:text-rose-400" title="Remove from log (lets this person win again)" onclick="A.deleteDraw(${w.id})"><i class="fa-solid fa-xmark"></i></button></span>
      </div>`).join('') : `<p class="text-xs text-slate-400 italic">No prizes awarded yet.</p>`;
  }

  async function act(action, payload, msg) { const r = await call(action, payload); if (r.ok && r.questions !== undefined) { applyData(r); if (msg) RQ.toast(msg, 'ok'); RQStage.refreshNow(); } return r; }
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
  const deletePlayer = id => { const p = data.players.find(x => x.id === id); if (confirm(`Remove ${p.name}? Their scores will be deleted.`)) act('player_delete', { id }, 'Player removed'); };
  const deleteDraw = id => { if (confirm('Remove this prize from the log? That person becomes eligible to win again.')) act('delete_draw', { id }, 'Removed'); };
  const resetScores = () => { if (confirm('Reset ALL scores and prize history for this event? Players stay joined.')) act('reset_scores', {}, 'Scores reset'); };
  const removePlayers = () => { if (confirm('Remove ALL players from this event? Everyone will have to join again.')) act('remove_players', {}, 'All players removed'); };
  function exportCsv(action) { window.location = `api.php?action=${action}&csrf=${encodeURIComponent(window.RQ_CSRF)}`; }
  async function logout() { await RQ.api('logout'); location.reload(); }

  /* ------------------------------------------------------------ init */
  function keys(e) {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) || e.ctrlKey || e.metaKey || e.altKey) return;
    if ($('tab-live').classList.contains('hidden') && !document.fullscreenElement) return;
    const k = e.key.toLowerCase();
    if (k === 'n') next(); else if (k === 'e' && live && live.phase === 'QUESTION') endQ();
    else if (k === 'w') draw(); else if (k === 'l') phase('LEADERBOARD'); else if (k === 'p') phase('PODIUM'); else if (k === 'f') fullscreen();
    else return;
    e.preventDefault();
  }

  document.addEventListener('DOMContentLoaded', () => {
    RQ.audio = RQ.createAudio('admin');
    $('volBox').innerHTML = RQ.volumeControlsHtml(); RQ.audio.apply();
    document.querySelectorAll('.tab-btn').forEach(b => b.onclick = () => setTab(b.dataset.tab));
    let t = 'live'; try { t = localStorage.getItem('rq_admin_tab') || 'live'; } catch (e) {}
    setTab(t);
    document.addEventListener('keydown', keys);
    loadData().then(() => resetForm());
    RQStage.init({ audio: true, onData: updateLive });
    setInterval(() => { if (!$('tab-event').classList.contains('hidden')) loadData(); }, 5000);
  });

  return { pullSearch, pullRun, renderQuestions, useBank, appendBank, renameBank, dupBank, deleteBank, newBank, previewBank, renderBanks, currentBankId, next, jump, endQ, draw, phase, quickAuto, fullscreen, saveQuestion, resetForm, editQ, dupQ, delQ, moveQ, applyAllTime, clearQuestions,
    openJson, closeJson, applyJson, loadFile, exportJson, modePicked, saveSettings, renameEvent, renameSession, newSession, newEvent, switchEvent,
    renamePlayer, deletePlayer, deleteDraw, resetScores, removePlayers, exportCsv, logout, renderPlayers };
})();
