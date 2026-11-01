/* RizQuiz live control: one big "next step" button, a ☰ menu for everything else, and a presenter dock. */
window.LV = (function () {
  const $ = id => document.getElementById(id);
  let data = null;   // admin_data (questions, banks, config)
  let live = null;   // latest stage data
  let qs = [];
  let busy = false, jumpBuiltFor = null, lastLiveQKey = null, action = null;
  const PHASE = { LOBBY: '📲 Players are joining', QUESTION: '⏱️ Question is live', REVEAL: '✅ Answer revealed',
    WINNER: '🎁 Prize winner', LEADERBOARD: '📊 Leaderboard', PODIUM: '🏆 Grand podium' };

  async function call(a, payload = {}, quiet = false) {
    const r = await RQ.api(a, payload);
    if (!r.ok) {
      if (r.code === 'AUTH') { RQ.toast(r.error, 'error'); setTimeout(() => location.href = 'login.php', 1200); }
      else if (!quiet) RQ.toast(r.error, 'error');
    }
    return r;
  }
  async function loadData() {
    const r = await call('admin_data', {}, true);
    if (!r.ok) return;
    data = r; qs = r.questions || [];
    $('quickAuto').checked = !!r.config.prizeEveryQuestion;
    $('liveBankSel').innerHTML = r.banks.map(b => `<option value="${b.id}" ${b.active ? 'selected' : ''}>${RQ.esc(b.name)} (${b.count})</option>`).join('');
    $('soundNames').textContent = `Question music: ${r.soundsChosen.question} · Podium: ${r.soundsChosen.podium}`;
    jumpBuiltFor = null;
    if (live) updateLive(live);
  }

  const pos = d => d.adhoc ? d.mainIndex : d.qIndex;
  function nextStep(d) {
    const n = pos(d) + 1, total = qs.length;
    if (n < total) return { label: pos(d) < 0 ? 'Start game' : 'Next question', sub: `Question ${n + 1} of ${total}`, icon: 'fa-play', cls: 'btn-go', run: () => start(n) };
    return { label: 'Show podium', sub: 'Reveal the champions', icon: 'fa-trophy', cls: 'btn-purple', run: () => phase('PODIUM') };
  }

  /** What the big button does right now. */
  function primaryAction(d) {
    const now = RQ.serverNow();
    if (!qs.length) return { label: 'No questions', sub: 'Add some in Setup', icon: 'fa-circle-exclamation', cls: '', disabled: true };
    switch (d.phase) {
      case 'LOBBY': return nextStep(d);
      case 'QUESTION':
        if (d.q && d.q.type === 'ideas' && d.q.ideaStage !== 'vote') return { label: 'Start voting', sub: 'Close idea sharing', icon: 'fa-check-to-slot', cls: 'btn-primary', run: endQ };
        return { label: 'Reveal answer', sub: 'End the timer now', icon: 'fa-eye', cls: 'btn-primary', run: endQ };
      case 'REVEAL':
        if (d.prizeMode && d.prizeMode !== 'none' && !d.roundDraws.length)
          return { label: 'Draw prize winner', sub: d.prizeEveryQuestion ? `Auto-draws in a moment · ${RQT.prizeLabel(d.prizeMode, d.q)}` : RQT.prizeLabel(d.prizeMode, d.q), icon: 'fa-gift', cls: 'btn-gold', run: draw };
        return nextStep(d);
      case 'WINNER':
        if (d.draw && now < d.drawAt + d.drawAnimMs) return { label: 'Drawing…', sub: 'Good luck everyone!', icon: 'fa-spinner fa-spin', cls: 'btn-gold', disabled: true };
        return nextStep(d);
      case 'LEADERBOARD': return nextStep(d);
      case 'PODIUM': return { label: 'Back to lobby', sub: 'Show the join QR code', icon: 'fa-qrcode', cls: '', run: () => phase('LOBBY') };
    }
    return nextStep(d);
  }

  function renderPrimary(d) {
    action = primaryAction(d);
    const b = $('btnPrimary');
    b.className = `btn ${action.cls || ''} btn-lg !py-2.5 !px-5 min-w-[13rem] !justify-start text-left ${d.phase === 'LOBBY' && pos(d) < 0 ? 'animate-pulse' : ''}`;
    b.disabled = !!action.disabled;
    $('primIcon').className = `fa-solid ${action.icon} text-xl`;
    $('primLabel').textContent = action.label;
    $('primSub').textContent = action.sub || '';
    $('dockPrimary').className = `btn ${action.cls || ''} btn-lg !py-2.5`;
    $('dockPrimary').disabled = !!action.disabled;
    $('dockPrimary').innerHTML = `<i class="fa-solid ${action.icon}"></i> ${RQ.esc(action.label)}`;
  }

  function updateLive(d) {
    live = d;
    if (!d.launched) { location.href = 'setup.php#launch'; return; }
    if (!data) return;
    const total = qs.length, p = pos(d);
    const nm = d.qName ? ` · ${d.qName}` : '';
    $('stQ').textContent = d.adhoc ? `⭐ Bonus question${nm}` : d.qIndex >= 0 ? `Question ${d.qIndex + 1} of ${total}${nm}` : `${data.bank.name} · ${total} questions`;
    $('stPhase').textContent = PHASE[d.phase] || d.phase;
    $('stPlayers').innerHTML = `<i class="fa-solid fa-users"></i> ${d.counts.active} playing`;
    renderPrimary(d);

    // auto-prize status — always visible
    const auto = !!d.prizeEveryQuestion, mode = data.config.prizeMode;
    $('autoPill').className = `chip !text-sm !py-1.5 cursor-pointer ${auto ? 'chip-gold' : ''}`;
    $('autoPill').innerHTML = auto ? `🎁 Auto prizes ON · ${RQ.MODE[mode].label}` : '🎁 Auto prizes OFF';
    if (document.activeElement !== $('quickAuto')) $('quickAuto').checked = auto;
    $('btnWinner').disabled = d.phase === 'QUESTION';
    $('dockWinner').disabled = d.phase === 'QUESTION';
    $('btnEnd').disabled = d.phase !== 'QUESTION';

    let hint;
    if (d.phase === 'LOBBY' && p < 0) hint = `Players scan the QR code to join. When you're ready, click <b class="text-white">Start game</b> (or press → / your clicker).`;
    else if (d.phase === 'QUESTION') hint = d.prizeMode && d.prizeMode !== 'none' ? `This question has a prize: <b class="text-white">${RQT.prizeLabel(d.prizeMode, d.q)}</b>` : 'No prize on this question.';
    else if (d.phase === 'REVEAL' && d.prizeMode !== 'none' && !d.roundDraws.length) hint = auto ? 'A prize winner will be drawn automatically.' : 'Click the gold button to draw this question\'s prize.';
    else hint = 'Press → or your clicker for the next step.';
    $('hint').innerHTML = hint;

    const k = d.session.id + ':' + d.roundId;
    if (k !== lastLiveQKey) {
      lastLiveQKey = k;
      $('drawMode').value = d.prizeMode && d.prizeMode !== 'none' ? d.prizeMode : (mode === 'mix' ? 'random_correct' : mode);
    }
    $('roundWinners').innerHTML = d.roundDraws.map(w =>
      `<span class="chip ${w.name ? 'chip-green' : ''} !text-[11px]">${w.name ? RQ.esc(w.emoji) + ' ' + RQ.esc(w.name) : 'No eligible winner'} · ${w.note === 'best_idea' ? 'Best idea' : RQ.MODE[w.mode].short}</span>`).join('');

    const sig = qs.length + ':' + p + ':' + qs.map(q => (q.name || '') + q.type).join('|');
    if (jumpBuiltFor !== sig) {
      jumpBuiltFor = sig;
      $('jumpSel').innerHTML = qs.map((q, i) => `<option value="${i}">Q${i + 1} ${(RQT.INFO[q.type] || {}).icon || ''} ${q.name ? '[' + RQ.esc(q.name) + '] ' : ''}${RQ.esc(q.text.length > 34 ? q.text.slice(0, 34) + '…' : q.text)}</option>`).join('');
      $('jumpSel').value = Math.min(Math.max(0, p + 1), Math.max(0, qs.length - 1));
    }
  }

  async function start(i, bankId = null) {
    if (busy) return; busy = true;
    RQ.audio && RQ.audio.stop();
    const r = await call('start_question', bankId ? { index: i, bankId } : { index: i });
    busy = false;
    if (r.ok) RQStage.refreshNow();
  }
  function primary() { if (action && !action.disabled && action.run && !busy) action.run(); }
  function next(fromMenu) {
    if (!live || !qs.length) return;
    if (live.phase === 'QUESTION' && !confirm('A question is still running. Skip to the next one?')) return;
    nextStep(live).run();
    if (fromMenu) menu(false);
  }
  function jump() {
    const i = parseInt($('jumpSel').value, 10);
    if (isNaN(i)) return;
    if (live && live.phase === 'QUESTION' && !confirm('A question is running. Stop it and jump?')) return;
    if (live && !live.adhoc && i <= live.qIndex && !confirm(`Replay Question ${i + 1}? Any answers already given to it today will be cleared and re-scored.`)) return;
    start(i); menu(false);
  }

  let pullTimer = null;
  function pullSearch() {
    clearTimeout(pullTimer);
    pullTimer = setTimeout(async () => {
      const term = $('pullInput').value.trim();
      const r = await call('question_search', { q: term }, true);
      if (!r.ok) return;
      const box = $('pullResults');
      box.classList.remove('hidden');
      box.innerHTML = r.hits.length ? r.hits.map(h => `
        <div class="flex items-center gap-2 p-2 rounded-xl hover:bg-white/10">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-bold truncate">${h.name ? `<span class="text-cyan-300">${RQ.esc(h.name)}</span>` : '<span class="text-muted">(no name)</span>'}
              <span class="text-[11px] font-semibold text-muted"> · ${h.loaded ? 'Q' + (h.index + 1) : RQ.esc(h.bankName) + ' Q' + (h.index + 1) + ' · bonus'}</span></p>
            <p class="text-xs text-muted truncate">${RQ.esc(h.text)}</p>
          </div>
          <button class="btn btn-go btn-sm shrink-0" onclick="LV.pullRun(${h.bankId}, ${h.index}, ${h.loaded})"><i class="fa-solid fa-play"></i> Run</button>
        </div>`).join('')
        : `<p class="text-sm text-muted italic p-2">${term ? 'No questions match.' : 'No named questions yet — name questions in Setup.'}</p>`;
    }, 150);
  }
  function pullRun(bankId, index, loaded) {
    $('pullResults').classList.add('hidden'); $('pullInput').value = '';
    if (live && live.phase === 'QUESTION' && !confirm('A question is running. Stop it and run this one?')) return;
    start(index, loaded ? null : bankId); menu(false);
  }

  async function endQ() { const r = await call('end_question'); if (r.ok) RQStage.refreshNow(); }
  async function draw() {
    if (busy || (live && live.phase === 'QUESTION')) return; busy = true;
    const r = await call('draw', { mode: $('drawMode').value });
    busy = false;
    if (r.ok) { RQStage.refreshNow(); if (!r.draw.name) RQ.toast('No eligible player for that prize type — try another type.', 'info'); }
  }
  async function phase(p) {
    if (p === 'PODIUM' && live && live.phase === 'QUESTION' && !confirm('A question is still running. End it and show the podium?')) return;
    const r = await call('set_phase', { phase: p });
    if (r.ok) { RQStage.refreshNow(); menu(false); }
  }
  async function setAuto(on) {
    const r = await call('save_config', { config: { prizeEveryQuestion: on } });
    if (r.ok) { data.config = r.config; $('quickAuto').checked = on; RQ.toast(on ? 'Automatic prizes ON' : 'Automatic prizes OFF — use Winner!', 'ok'); RQStage.refreshNow(); }
  }
  function resetGame() {
    menu(false);
    RQ.resetGameDialog({ call, data: data || {}, onDone: () => { loadData(); RQStage.refreshNow(); } });
  }
  function toggleAuto() { setAuto(!$('quickAuto').checked); }
  async function useBank(id) {
    if (!id || id === data.bank.id) { RQ.toast('That bank is already loaded.', 'info'); return; }
    if (live && live.phase === 'QUESTION') { RQ.toast('Wait for the current question to end.', 'error'); return; }
    const b = data.banks.find(x => x.id === id);
    if (!confirm(`Load “${b.name}”? The game returns to the lobby and starts from question 1 of this bank. Scores are kept.`)) { $('liveBankSel').value = data.bank.id; return; }
    const r = await call('bank_activate', { id });
    if (r.ok) { await loadData(); RQStage.refreshNow(); RQ.toast(`Loaded “${b.name}”`, 'ok'); menu(false); }
  }

  /* ---------------- menu drawer ---------------- */
  function menu(open) {
    $('drawer').classList.toggle('translate-x-full', !open);
    $('drawerBg').classList.toggle('hidden', !open);
    if (!open) $('pullResults').classList.add('hidden');
  }

  /* ---------------- presentation mode ---------------- */
  let dockTimer = null;
  function presenting() { return $('shell').classList.contains('presenting'); }
  function showDock() {
    if (!presenting()) return;
    $('dock').style.opacity = '1';
    clearTimeout(dockTimer);
    dockTimer = setTimeout(() => { $('dock').style.opacity = '0'; }, 3000);
  }
  function present() {
    const on = !presenting();
    $('shell').classList.toggle('presenting', on);
    $('dock').classList.toggle('hidden', !on);
    if (on) { showDock(); if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(() => {}); }
    else if (document.fullscreenElement) document.exitFullscreen();
    setTimeout(() => window.dispatchEvent(new Event('resize')), 300);
  }
  document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement && presenting()) { $('shell').classList.remove('presenting'); $('dock').classList.add('hidden'); setTimeout(() => window.dispatchEvent(new Event('resize')), 300); }
  });
  document.addEventListener('mousemove', showDock);

  function keys(e) {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) || e.ctrlKey || e.metaKey || e.altKey) return;
    const k = e.key.toLowerCase();
    if (['arrowright', 'pagedown', ' ', 'enter', 'n'].includes(k)) primary();    // presentation clickers send → / Page Down
    else if (k === 'w') draw(); else if (k === 'l') phase('LEADERBOARD'); else if (k === 'p') phase('PODIUM');
    else if (k === 'f') present(); else if (k === 'escape') { menu(false); if (presenting()) present(); }
    else if (k === 'e' && live && live.phase === 'QUESTION') endQ();
    else return;
    e.preventDefault(); showDock();
  }

  /* links from the PowerPoint deck: live.php?run=3&bank=12  or  ?show=PODIUM */
  async function handleLinkParams() {
    const u = new URLSearchParams(location.search);
    if (u.has('run')) await start(parseInt(u.get('run'), 10), u.get('bank') && +u.get('bank') !== data.bank.id ? +u.get('bank') : null);
    if (u.get('show')) await phase(u.get('show').toUpperCase());
    if (u.has('run') || u.has('show')) history.replaceState(null, '', 'live.php');
  }

  document.addEventListener('DOMContentLoaded', async () => {
    RQ.audio = RQ.createAudio('live');
    $('volBox').innerHTML = RQ.volumeControlsHtml(); RQ.audio.apply();
    document.addEventListener('keydown', keys);
    await loadData();
    RQStage.init({ audio: true, onData: updateLive });
    handleLinkParams();
    setInterval(() => { if (live) renderPrimary(live); }, 500);   // e.g. "Drawing…" turns back into "Next question"
  });

  return { primary, next, jump, endQ, draw, phase, setAuto, toggleAuto, resetGame, useBank, present, menu, pullSearch, pullRun };
})();
