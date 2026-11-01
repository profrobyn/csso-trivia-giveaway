/* RizQuiz shared helpers: API calls, formatting, audio. */
window.RQ = (function () {
  const MODE = {
    random_correct: { label: 'Random Correct Answer', short: 'Random correct', icon: 'fa-dice', emoji: '🎲',
      rule: 'Answer correctly to be entered into a random prize draw!' },
    random_player: { label: 'Random Player', short: 'Random player', icon: 'fa-shuffle', emoji: '🎟️',
      rule: 'Everyone playing is in the draw — right answer or not!' },
    fastest: { label: 'Fastest Correct Answer', short: 'Fastest correct', icon: 'fa-bolt', emoji: '⚡',
      rule: 'The quickest correct answer wins the prize!' },
    mix: { label: 'Mix It Up', short: 'Mix', icon: 'fa-wand-magic-sparkles', emoji: '✨',
      rule: 'The prize rules change from question to question!' },
    none: { label: 'No Prize', short: 'No prize', icon: 'fa-ban', emoji: '', rule: '' },
  };

  async function api(action, data = {}) {
    const body = new FormData();
    if (RQ.game && data.g === undefined) body.append('g', RQ.game);
    Object.entries(data).forEach(([k, v]) => body.append(k, (v !== null && typeof v === 'object') ? JSON.stringify(v) : v));
    const headers = {};
    if (window.RQ_CSRF) headers['X-CSRF'] = window.RQ_CSRF;
    try {
      const res = await fetch('api.php?action=' + encodeURIComponent(action), { method: 'POST', body, headers, cache: 'no-store' });
      const txt = await res.text();
      try { return JSON.parse(txt); } catch (e) { return { ok: false, error: 'Server error: ' + txt.slice(0, 200) }; }
    } catch (e) {
      return { ok: false, error: 'Connection problem — retrying…', offline: true };
    }
  }

  const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const ordinal = n => { const s = ['th', 'st', 'nd', 'rd'], v = n % 100; return n + (s[(v - 20) % 10] || s[v] || s[0]); };
  const letter = i => String.fromCharCode(65 + i);
  const fmt = n => Number(n || 0).toLocaleString();

  /* ---------- clock sync: serverNow() ≈ server's Date.now() ---------- */
  let offset = 0, haveOffset = false;
  function syncClock(serverNow) {
    const o = serverNow - Date.now();
    offset = haveOffset ? (offset * 0.7 + o * 0.3) : o; // smooth
    haveOffset = true;
  }
  const serverNow = () => Date.now() + offset;

  /* ---------- audio with per-page volume memory ---------- */
  function createAudio(pageKey) {
    const kV = 'rq_vol_' + pageKey, kM = 'rq_muted_' + pageKey;
    let vol = 1, muted = false;
    try { vol = parseFloat(localStorage.getItem(kV) ?? '1'); muted = localStorage.getItem(kM) === 'true'; } catch (e) {}
    if (isNaN(vol)) vol = 1;
    const tracks = { question: new Audio('Question.mp3'), podium: new Audio('Podium.mp3') };
    Object.values(tracks).forEach(a => { a.loop = true; a.preload = 'auto'; });
    let current = null;
    function apply() {
      Object.values(tracks).forEach(a => a.volume = muted ? 0 : Math.max(0, Math.min(1, vol)));
      try { localStorage.setItem(kV, vol); localStorage.setItem(kM, muted); } catch (e) {}
      const pct = Math.round((muted ? 0 : vol) * 100);
      document.querySelectorAll('.volume-pct-display').forEach(el => el.textContent = pct + '%');
      document.querySelectorAll('.mute-btn-icon').forEach(b => b.innerHTML = (muted || vol === 0)
        ? '<i class="fa-solid fa-volume-xmark text-rose-400"></i>' : '<i class="fa-solid fa-volume-high text-emerald-400"></i>');
    }
    function play(name) {
      if (current === name) return;
      stop();
      current = name;
      const a = tracks[name]; if (!a) return;
      a.currentTime = 0;
      const p = a.play(); if (p) p.catch(() => {});
    }
    function stop() { Object.values(tracks).forEach(a => a.pause()); current = null; }
    apply();
    /** Switch to the host's chosen music files (e.g. "Question2.mp3"). */
    function setSources(src) {
      if (!src) return;
      ['question', 'podium'].forEach(k => {
        if (!src[k]) return;
        const want = new URL(src[k], document.baseURI).href;
        if (tracks[k].src !== want) { const wasPlaying = current === k; tracks[k].pause(); tracks[k].src = want; if (wasPlaying) { current = null; play(k); } }
      });
    }
    return {
      play, stop, apply, setSources,
      adjust(d) { muted = false; vol = Math.max(0, Math.min(1, Math.round((vol + d) * 100) / 100)); apply(); },
      toggleMute() { muted = !muted; apply(); },
      get playing() { return current; },
    };
  }

  function volumeControlsHtml() {
    return `<div class="flex items-center gap-1 glass-2 p-1 !rounded-xl">
      <button onclick="RQ.audio.toggleMute()" class="mute-btn-icon btn btn-sm btn-ghost !px-2" title="Mute/Unmute"></button>
      <button onclick="RQ.audio.adjust(-0.25)" class="btn btn-sm btn-ghost !px-2" title="Quieter"><i class="fa-solid fa-minus"></i></button>
      <span class="volume-pct-display text-xs font-extrabold text-amber-200 min-w-[38px] text-center">100%</span>
      <button onclick="RQ.audio.adjust(0.25)" class="btn btn-sm btn-ghost !px-2" title="Louder"><i class="fa-solid fa-plus"></i></button>
    </div>`;
  }

  function toast(msg, kind = 'info') {
    let box = document.getElementById('rqToasts');
    if (!box) { box = document.createElement('div'); box.id = 'rqToasts'; box.className = 'fixed bottom-5 left-1/2 -translate-x-1/2 z-[100] flex flex-col gap-2 items-center w-[92%] max-w-md pointer-events-none'; document.body.appendChild(box); }
    const el = document.createElement('div');
    const colors = { info: 'linear-gradient(135deg,#6d28d9,#3b82f6)', error: 'linear-gradient(135deg,#e11d48,#9f1239)', ok: 'linear-gradient(135deg,#059669,#0891b2)' };
    const icons = { info: 'fa-circle-info', error: 'fa-triangle-exclamation', ok: 'fa-circle-check' };
    el.className = 'px-4 py-3 rounded-2xl shadow-2xl text-sm font-bold text-white animate-pop-in flex items-center gap-2 border border-white/20';
    el.style.background = colors[kind] || colors.info;
    el.innerHTML = `<i class="fa-solid ${icons[kind] || icons.info}"></i><span></span>`;
    el.querySelector('span').textContent = msg;
    box.appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  /* answer tile shapes (also helps color-blind players) */
  const SHAPES = ['fa-play fa-rotate-270', 'fa-diamond', 'fa-circle', 'fa-square', 'fa-star', 'fa-heart'];
  const shape = i => `<span class="shape"><i class="fa-solid ${SHAPES[i % SHAPES.length]}"></i></span>`;

  /* circular countdown */
  function timerRing(size, stroke = 10) {
    const r = (size - stroke) / 2, c = 2 * Math.PI * r;
    return `<div class="timer-ring" style="width:${size}px;height:${size}px">
      <svg width="${size}" height="${size}"><defs><linearGradient id="tgrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fde047"/><stop offset=".5" stop-color="#ff4fd8"/><stop offset="1" stop-color="#22d3ee"/></linearGradient></defs>
      <circle class="track" cx="${size / 2}" cy="${size / 2}" r="${r}" fill="none" stroke-width="${stroke}"/>
      <circle class="bar" cx="${size / 2}" cy="${size / 2}" r="${r}" fill="none" stroke-width="${stroke}" stroke-dasharray="${c}" stroke-dashoffset="0" data-c="${c}"/></svg>
      <span class="num" style="font-size:${Math.round(size * .36)}px">0</span></div>`;
  }
  function setRing(el, left, total) {
    if (!el) return;
    const bar = el.querySelector('.bar'), c = +bar.dataset.c;
    bar.style.strokeDashoffset = c * (1 - Math.max(0, Math.min(1, total ? left / total : 0)));
    el.querySelector('.num').textContent = Math.ceil(left);
    el.classList.toggle('urgent', left <= 5 && left > 0);
  }

  /* ---------- Pro branding: colors + logo ---------- */
  let brandSig = null;
  function applyBrand(b) {
    const sig = JSON.stringify(b || null);
    if (sig === brandSig) return;
    brandSig = sig;
    let st = document.getElementById('rqBrand');
    if (!st) { st = document.createElement('style'); st.id = 'rqBrand'; document.head.appendChild(st); }
    const v = [];
    if (b && b.primary) v.push(`--pink:${b.primary}`, `--cyan:${b.primary}`);
    if (b && b.accent) v.push(`--violet:${b.accent}`, `--gold:${b.accent}`);
    if (b && b.bg) v.push(`--bg:${b.bg}`);
    st.textContent = v.length ? `:root{${v.join(';')}}` : '';
    document.querySelectorAll('.logo-badge').forEach(el => {
      if (!el.dataset.orig) el.dataset.orig = el.innerHTML;
      if (b && b.logo) { el.classList.add('has-img'); el.innerHTML = `<img src="${b.logo}" alt="">`; }
      else { el.classList.remove('has-img'); el.innerHTML = el.dataset.orig; }
    });
  }

  /* ---------- Excel backup (Pro and up) — shared by Setup and Live ---------- */
  async function excelBackup(call) {
    if (typeof XLSX === 'undefined') { toast('The Excel tool is still loading — try again in a second.', 'error'); return false; }
    const r = await call('export_data'); if (!r || !r.ok) return false;
    const wb = XLSX.utils.book_new();
    Object.entries(r.sheets).forEach(([name, rows]) => {
      const ws = rows.length ? XLSX.utils.json_to_sheet(rows) : XLSX.utils.aoa_to_sheet([['(nothing yet)']]);
      if (rows.length) ws['!cols'] = Object.keys(rows[0]).map(k => ({ wch: Math.min(60, Math.max(k.length, ...rows.map(x => String(x[k] ?? '').length)) + 2) }));
      XLSX.utils.book_append_sheet(wb, ws, name.slice(0, 31));
    });
    XLSX.writeFile(wb, `${(r.event || 'RizQuiz').replace(/[^\w\- ]+/g, '').trim().replace(/\s+/g, '_')}_results_${new Date().toLocaleDateString('en-CA')}.xlsx`);
    toast('Excel file downloaded', 'ok');
    return true;
  }

  /* ---------- Reset game: big red warning, optional backup, type RESET to confirm ---------- */
  async function resetGameDialog({ call, data, onDone }) {
    const old = document.getElementById('rqResetModal'); if (old) old.remove();
    const fresh = await call('admin_data', {}, true);      // live player count, not the one from page load
    if (fresh && fresh.ok) data = fresh;
    const players = (data.players || []).length;
    const days = (data.sessions || []).length || 1;
    const canBackup = !!(data.plan && data.plan.export);
    const ev = esc((data.event && data.event.name) || 'this event');
    const m = document.createElement('div');
    m.id = 'rqResetModal';
    m.className = 'fixed inset-0 z-[90] grid place-items-center p-4';
    m.style.background = 'rgba(5,4,20,.78)';
    m.innerHTML = `
      <div role="alertdialog" aria-modal="true" aria-labelledby="rqResetTitle" class="glass !rounded-3xl w-full max-w-xl max-h-[92vh] overflow-y-auto p-6 md:p-8 space-y-5 animate-pop-in"
           style="background:rgba(40,10,30,.97);border:2px solid rgba(244,63,94,.75);box-shadow:0 0 80px rgba(244,63,94,.35)">
        <div class="flex items-start gap-4">
          <div class="shrink-0 w-16 h-16 rounded-2xl grid place-items-center text-4xl" style="background:rgba(244,63,94,.25)">⚠️</div>
          <div>
            <h2 id="rqResetTitle" class="font-display text-3xl font-bold text-rose-200">Reset the game?</h2>
            <p class="text-rose-100/90 font-semibold mt-1">All player data will be permanently deleted. This cannot be undone.</p>
          </div>
        </div>
        <div class="rounded-2xl p-4 space-y-1.5 text-sm" style="background:rgba(244,63,94,.12);border:1px solid rgba(244,63,94,.4)">
          <p class="font-bold text-rose-100">For <b>${ev}</b>${days > 1 ? ` (all ${days} days)` : ''} this removes:</p>
          <p>🧑‍🤝‍🧑 <b>${players}</b> player${players === 1 ? '' : 's'}, their names, emojis and rejoin codes. Everyone has to join again.</p>
          <p>🏆 All scores, answers, word-cloud words, ideas and votes</p>
          <p>🎁 Every prize winner on record</p>
          <p class="text-muted pt-1">✅ Kept: your questions, banks, settings, branding and the event's days.</p>
        </div>
        ${canBackup ? `
        <div class="rounded-2xl p-4 space-y-2" style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.45)">
          <p class="font-bold text-emerald-200">💾 Strongly recommended: back up first</p>
          <p class="text-sm text-muted">Download the Excel file of standings, answers, ideas and prize winners before you reset.</p>
          <button id="rqResetBackup" class="btn btn-go"><i class="fa-solid fa-file-excel"></i> Download backup (Excel)</button>
          <p id="rqResetBacked" class="hidden text-sm font-bold text-emerald-300">✓ Backup downloaded</p>
        </div>` : `
        <div class="rounded-2xl p-4 text-sm text-muted" style="background:rgba(255,255,255,.05);border:1px solid var(--line)">
          💡 Need a record? Take a screenshot of the leaderboard now. Excel backups are included with the Pro plan and up.
        </div>`}
        <div class="space-y-2">
          <label for="rqResetWord" class="lbl">Type <b class="text-rose-200">RESET</b> to confirm</label>
          <input id="rqResetWord" class="field" autocomplete="off" spellcheck="false" placeholder="RESET">
        </div>
        <div class="flex flex-wrap gap-2 justify-end">
          <button id="rqResetCancel" class="btn">Cancel — keep everything</button>
          <button id="rqResetGo" class="btn btn-danger !bg-rose-600 !text-white !border-rose-400 font-bold" disabled><i class="fa-solid fa-trash-can"></i> Delete all player data</button>
        </div>
      </div>`;
    document.body.appendChild(m);
    const $m = id => m.querySelector('#' + id);
    let backed = false;
    const close = () => { m.remove(); document.removeEventListener('keydown', onKey, true); };
    const ready = () => $m('rqResetWord').value.trim().toUpperCase() === 'RESET';
    const onKey = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } else e.stopPropagation(); };
    document.addEventListener('keydown', onKey, true);   // keep the live page's clicker keys from firing underneath
    m.addEventListener('click', e => { if (e.target === m) close(); });
    $m('rqResetCancel').onclick = close;
    $m('rqResetWord').oninput = () => { $m('rqResetGo').disabled = !ready(); };
    $m('rqResetWord').onkeydown = e => { if (e.key === 'Enter' && ready()) $m('rqResetGo').click(); };
    if (canBackup) $m('rqResetBackup').onclick = async () => {
      if (await excelBackup(call)) { backed = true; $m('rqResetBacked').classList.remove('hidden'); }
    };
    $m('rqResetGo').onclick = async () => {
      if (!ready()) return;
      if (canBackup && !backed && players > 0 && !confirm('You have not downloaded a backup. Delete all player data anyway?')) return;
      $m('rqResetGo').disabled = true;
      const r = await call('reset_game', { confirm: 'RESET' });
      if (r && r.ok) { close(); toast('Game reset — all players removed', 'ok'); if (onDone) onDone(r); }
      else $m('rqResetGo').disabled = false;
    };
    setTimeout(() => $m(canBackup ? 'rqResetBackup' : 'rqResetCancel').focus(), 50);
  }

  return { applyBrand, api, esc, ordinal, letter, fmt, MODE, syncClock, serverNow, createAudio, volumeControlsHtml, toast, shape, timerRing, setRing, excelBackup, resetGameDialog, audio: null, game: null };
})();
