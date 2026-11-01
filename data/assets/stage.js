/* RizQuiz stage renderer — shared by live.php (host) and stage.php (projector). */
window.RQStage = (function () {
  const $ = id => document.getElementById(id);
  const SCREENS = ['sClosed', 'sLobby', 'sQuestion', 'sDraw', 'sWinner', 'sBoard', 'sPodium'];
  let D = null, opts = {}, waveSince = 0, lastQrUrl = null, renderedQKey = null;
  let celebratedDraw = null, celebratedPodium = null, pollTimer = null, ringSize = 0;

  function show(id) { SCREENS.forEach(s => $(s).classList.toggle('hidden', s !== id)); }

  function ensureRing() {
    const box = $('stageRoot');
    const size = Math.round(Math.max(58, Math.min(150, Math.min(box.clientHeight * 0.13, box.clientWidth * 0.08))));
    if (size !== ringSize) { ringSize = size; $('sTimerBox').innerHTML = RQ.timerRing(size, Math.max(6, size / 11)); }
  }

  function boardHtml(d) {
    const title = d.scope === 'session' ? `Today · ${RQ.esc(d.session.label)}` : 'All days combined';
    let body;
    if (d.locked) {
      body = `<div class="flex-1 flex flex-col items-center justify-center text-center gap-3 p-6">
        <div class="text-[clamp(3rem,7vw,7rem)] bob">🤫</div>
        <h4 class="font-display font-bold text-[clamp(1.4rem,2.6vw,3rem)] text-amber-200">Rankings locked for the finale!</h4>
        <p class="text-muted text-[clamp(.9rem,1.3vw,1.6rem)]">The winners will be revealed on the Grand Podium.</p></div>`;
    } else if (!d.leaderboard.length) {
      body = `<div class="flex-1 grid place-items-center text-muted text-[clamp(1rem,1.6vw,2rem)]">No scores yet — answer fast!</div>`;
    } else {
      const top = Math.max(1, d.leaderboard[0].score);
      body = `<div class="flex-1 min-h-0 flex flex-col gap-[clamp(.3rem,.9vh,.8rem)] overflow-hidden">` + d.leaderboard.map((p, i) => {
        const medal = ['🥇', '🥈', '🥉'][i] || `<span class="text-muted font-bold">#${i + 1}</span>`;
        return `<div class="lb-row rise-in" style="animation-delay:${i * 60}ms">
          <span class="bar" style="width:${Math.max(4, p.score / top * 100)}%"></span>
          <span class="w-[2.2em] text-center">${medal}</span>
          <span class="text-[1.35em] leading-none">${RQ.esc(p.emoji)}</span>
          <span class="font-bold truncate flex-1 min-w-0">${RQ.esc(p.name)}</span>
          <span class="font-display font-bold text-emerald-300">${RQ.fmt(p.score)}</span></div>`;
      }).join('') + `</div>`;
    }
    return `<div class="glass !rounded-[2rem] p-[clamp(.8rem,2vw,2rem)] flex-1 min-h-0 flex flex-col gap-3">
      <div class="flex items-center justify-between gap-3">
        <h3 class="font-display font-bold text-[clamp(1.2rem,2.2vw,2.6rem)]"><i class="fa-solid fa-ranking-star text-amber-300"></i> Leaderboard</h3>
        <span class="chip !text-[clamp(.65rem,.9vw,1rem)]">${title}</span></div>${body}</div>`;
  }

  /* Only redraw the board when the scores really change, so it doesn't replay its slide-in animation every poll. */
  function putBoard(d) {
    const html = boardHtml(d);
    document.querySelectorAll('.sBoardBox').forEach(b => { if (b.dataset.html !== html) { b.dataset.html = html; b.innerHTML = html; } });
  }

  function rulesHtml(d) {
    const chips = ['<span class="chip chip-pink !text-[clamp(.7rem,1vw,1.15rem)]">⚡ Faster = more points</span>'];
    if (d.prizeEveryQuestion) chips.push('<span class="chip chip-green !text-[clamp(.7rem,1vw,1.15rem)]">🎁 Prize after every question</span>');
    else chips.push('<span class="chip chip-green !text-[clamp(.7rem,1vw,1.15rem)]">🎁 Surprise prizes</span>');
    if (d.scope === 'event') chips.push('<span class="chip chip-cyan !text-[clamp(.7rem,1vw,1.15rem)]">📅 Scores add up every day</span>');
    return chips.join('');
  }

  function renderQr(url) {
    if (url === lastQrUrl || typeof QRCode === 'undefined') return;
    lastQrUrl = url;
    $('sQr').innerHTML = ''; $('sQrSmall').innerHTML = '';
    new QRCode($('sQr'), { text: url, width: 512, height: 512, correctLevel: QRCode.CorrectLevel.M });
    new QRCode($('sQrSmall'), { text: url, width: 128, height: 128 });
    $('sUrl').textContent = url.replace(/^https?:\/\//, '');
  }

  let lastWifi = null;
  function renderWifi(d) {
    const on = !!d.wifiName;
    $('sWifiBox').classList.toggle('hidden', !on); $('sWifiBox').classList.toggle('flex', on);
    $('sStep2').classList.toggle('hidden', !on);
    if (!on || typeof QRCode === 'undefined') return;
    const esc = v => String(v).replace(/([\\;,:"])/g, '\\$1');
    const str = `WIFI:T:${d.wifiPass ? 'WPA' : 'nopass'};S:${esc(d.wifiName)};${d.wifiPass ? 'P:' + esc(d.wifiPass) + ';' : ''};`;
    $('sWifiName').textContent = d.wifiName;
    $('sWifiPassLine').textContent = d.wifiPass ? 'Password: ' + d.wifiPass : 'No password';
    if (str === lastWifi) return;
    lastWifi = str;
    $('sWifiQr').innerHTML = '';
    new QRCode($('sWifiQr'), { text: str, width: 320, height: 320, correctLevel: QRCode.CorrectLevel.M });
  }

  function renderQuestion(d) {
    const key = d.session.id + ':' + d.roundId;
    if (key !== renderedQKey) {
      renderedQKey = key;
      $('sQText').textContent = d.q.text;
      const n = (d.q.options || []).length;
      $('sOptions').className = 's-options n' + n;
      $('sOptions').innerHTML = d.q.options.map((o, i) => `
        <div id="sOpt${i}" class="ans ans-${i} rise-in" style="animation-delay:${i * 80}ms"><span class="fill"></span>${RQ.shape(i)}
          <span class="txt">${RQ.esc(o)}</span><span class="count"></span></div>`).join('');
    }
    $('sQNum').innerHTML = d.adhoc ? '⭐ Bonus Question' : `Question ${d.qIndex + 1} <span class="opacity-60">/ ${d.totalQ}</span>`;
    $('sAnswered').textContent = `${d.counts.answered}/${d.counts.active}`;
    const m = RQ.MODE[d.prizeMode];
    const showPrize = d.announcePrize && d.prizeMode && d.prizeMode !== 'none' && m;
    $('sPrizeChip').classList.toggle('hidden', !showPrize);
    if (showPrize) $('sPrizeChip').innerHTML = `${m.emoji} Prize: ${RQT.prizeLabel(d.prizeMode, d.q)}`;
    $('sGetReadyPrize').textContent = showPrize ? `${m.emoji} This round's prize: ${RQT.prizeLabel(d.prizeMode, d.q)} — ${RQT.prizeRule(d.prizeMode, d.q)}` : '';
    const info = RQT.INFO[d.q.type] || RQT.INFO.mc;
    const showType = d.q.type !== 'mc';
    $('sTypeChip').classList.toggle('hidden', !showType);
    if (showType) $('sTypeChip').textContent = `${info.icon} ${info.label}${d.q.type === 'ideas' ? (d.q.ideaStage === 'vote' && d.phase === 'QUESTION' ? ' · voting' : d.phase === 'QUESTION' ? ' · sharing' : '') : ''}`;

    // non multiple-choice questions draw their own area
    const custom = RQT.stageArea($('sTypeArea'), d);
    $('sTypeArea').classList.toggle('hidden', !custom);
    $('sOptions').classList.toggle('hidden', custom);
    const anyReveal = d.phase !== 'QUESTION';
    $('sStats').classList.toggle('hidden', !(anyReveal || (!d.q.scored && d.q.type !== 'ideas')));
    $('sCorrectStats').innerHTML = RQT.statsLine(d);

    const revealed = d.phase !== 'QUESTION' && d.q.correctIndex !== undefined;
    const total = d.stats ? d.stats.reduce((a, b) => a + b, 0) : 0;
    d.q.options.forEach((o, i) => {
      const el = $('sOpt' + i); if (!el) return;
      el.classList.toggle('revealed', revealed);
      el.classList.toggle('right', revealed && i === d.q.correctIndex);
      el.classList.toggle('wrong', revealed && i !== d.q.correctIndex);
      const n = d.stats ? (d.stats[i] || 0) : 0;
      el.querySelector('.count').textContent = revealed ? n : '';
      el.querySelector('.fill').style.width = revealed && total ? (n / total * 100) + '%' : '0';
    });
    const note = revealed ? (d.q.note || '') : '';
    $('sNote').classList.toggle('hidden', !note);
    if ($('sNoteText').textContent !== note) $('sNoteText').textContent = note;
  }

  function renderWinner(d) {
    const w = d.draw;
    const m = RQ.MODE[w.mode] || RQ.MODE.random_correct;
    $('sWinBadge').innerHTML = w.note === 'best_idea' ? '💡 Best idea prize' : `${m.emoji} ${w.qIndex >= 0 && d.q ? RQT.prizeLabel(w.mode, d.q) : m.label} prize`;
    if (w.name) {
      $('sWinEmoji').textContent = w.emoji || '👑';
      $('sWinName').textContent = w.name;
      $('sWinSub').textContent = w.note === 'best_idea' ? 'Shared the most-nominated idea!' : w.mode === 'fastest' && w.ms !== null && d.q && d.q.scored ? `⚡ Correct in ${(w.ms / 1000).toFixed(2)} seconds!`
        : (d.adhoc && w.qIndex >= 0 ? 'Wins the bonus question prize!' : d.qIndex >= 0 && w.qIndex >= 0 ? `Wins the Question ${w.qIndex + 1} prize!` : 'Wins a prize!');
    } else {
      $('sWinEmoji').textContent = '🤷';
      $('sWinName').textContent = 'No winner this time';
      $('sWinSub').textContent = w.note === 'all_won' ? 'Everyone eligible has already won a prize!'
        : w.note === 'no_players' ? 'No players were available for this draw.' : w.note === 'no_ideas' ? 'No idea got a nomination this time.' : 'Nobody answered correctly this time.';
    }
    const others = d.roundDraws.filter(x => x.id !== w.id && x.name);
    $('sWinOthers').innerHTML = others.length ? `<p class="uppercase tracking-widest font-bold text-[.8em]">Also won this round</p>` +
      others.map(x => `<p>${RQ.esc(x.emoji)} ${RQ.esc(x.name)} · ${RQ.MODE[x.mode].short}</p>`).join('') : '';
    if (celebratedDraw !== w.id) {
      celebratedDraw = w.id;
      if (w.name && typeof confetti === 'function') {
        confetti({ particleCount: 160, spread: 90, origin: { y: 0.6 } });
        setTimeout(() => confetti({ particleCount: 90, angle: 60, spread: 70, origin: { x: 0 } }), 350);
        setTimeout(() => confetti({ particleCount: 90, angle: 120, spread: 70, origin: { x: 1 } }), 550);
      }
    }
  }

  function renderPodium(d) {
    const b = d.leaderboard;
    [1, 2, 3].forEach(n => {
      const p = b[n - 1];
      $('sP' + n + 'e').textContent = p ? p.emoji : '';
      $('sP' + n + 'n').textContent = p ? p.name : '—';
      $('sP' + n + 's').textContent = p ? RQ.fmt(p.score) + ' pts' : '';
    });
    const su = d.signupUrl, box = $('sSignup');
    box.classList.toggle('hidden', !su);
    if (su && box.dataset.u !== su) {
      box.dataset.u = su;
      $('sSignupUrl').textContent = su.replace(/^https?:\/\//, '');
      $('sSignupQr').innerHTML = '';
      if (typeof QRCode !== 'undefined') new QRCode($('sSignupQr'), { text: su, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
    }
    $('sPodiumScope').textContent = d.scope === 'session' ? `Final standings · ${d.session.label}` : `Final standings · ${d.event.name}`;
    $('sPodiumRest').innerHTML = b.slice(3, 10).map(p => `<span class="chip !text-[clamp(.75rem,1.1vw,1.3rem)] !py-[.35em]">#${p.rank} ${RQ.esc(p.emoji)} ${RQ.esc(p.name)} · ${RQ.fmt(p.score)}</span>`).join('');
    if (celebratedPodium !== d.version) {
      celebratedPodium = d.version;
      if (typeof confetti === 'function') {
        setTimeout(() => confetti({ particleCount: 220, spread: 110, origin: { y: .5 } }), 1800);
        setTimeout(() => confetti({ particleCount: 140, spread: 140, origin: { y: .3 } }), 2600);
      }
    }
  }

  function render() {
    const d = D; if (!d) return;
    const now = RQ.serverNow();
    const audioOn = opts.audio !== false && RQ.audio;
    if (audioOn && d.sounds) RQ.audio.setSources(d.sounds);
    RQ.applyBrand(d.brand);
    if (!d.launched) {
      show('sClosed');
      if (audioOn) RQ.audio.stop();
      return;
    }
    renderQr(d.joinUrl);
    $('sCode').textContent = d.gameCode; $('sCodeSmall').textContent = d.gameCode;
    switch (d.phase) {
      case 'LOBBY':
        show('sLobby');
        $('sLobbyTitle').textContent = d.title;
        $('sLobbyEvent').textContent = `${d.event.name} · ${d.session.label}`;
        $('sLobbyCount').textContent = d.counts.active;
        const fk = (d.faces || []).map(f => f.emoji + f.name).join('|');
        if ($('sFaces').dataset.k !== fk) { $('sFaces').dataset.k = fk; $('sFaces').innerHTML = (d.faces || []).map(f => `<span class="face" title="${RQ.esc(f.name)}">${RQ.esc(f.emoji)}</span>`).join(''); }
        $('sWifiBanner').classList.toggle('hidden', !d.showWifiWarning);
        renderWifi(d);
        $('sWifiText').textContent = d.wifiText;
        { const r = rulesHtml(d); if ($('sRules').dataset.html !== r) { $('sRules').dataset.html = r; $('sRules').innerHTML = r; } }
        if (audioOn) RQ.audio.stop();
        break;
      case 'QUESTION': case 'REVEAL':
        if (!d.q) { show('sLobby'); break; }
        show('sQuestion'); ensureRing(); renderQuestion(d);
        if (audioOn) { if (d.phase === 'QUESTION') RQ.audio.play('question'); else RQ.audio.stop(); }
        break;
      case 'WINNER':
        if (audioOn) RQ.audio.stop();
        if (!d.draw) { show('sBoard'); }
        else if (now < d.drawAt + d.drawAnimMs) show('sDraw');
        else { show('sWinner'); renderWinner(d); }
        putBoard(d);
        break;
      case 'LEADERBOARD':
        if (audioOn) RQ.audio.stop();
        show('sBoard');
        putBoard(d);
        break;
      case 'PODIUM':
        show('sPodium'); renderPodium(d);
        if (audioOn) RQ.audio.play('podium');
        break;
    }
  }

  /* fast ticker for timers, countdown and the prize reel */
  function tick() {
    const d = D; if (!d || !d.launched) return;
    const now = RQ.serverNow();
    if ((d.phase === 'QUESTION' || d.phase === 'REVEAL') && d.q) {
      const gr = $('sGetReady');
      const ring = $('sTimerBox').firstElementChild;
      if (d.phase === 'QUESTION' && now < d.startedAt) {
        gr.classList.remove('hidden');
        const n = String(Math.ceil((d.startedAt - now) / 1000));
        if ($('sGetReadyNum').textContent !== n) $('sGetReadyNum').textContent = n;
        RQ.setRing(ring, d.timeLimit, d.timeLimit);
      } else {
        gr.classList.add('hidden');
        const left = d.phase === 'QUESTION' ? Math.max(0, (d.startedAt + d.timeLimit * 1000 - now) / 1000) : 0;
        RQ.setRing(ring, left, d.timeLimit);
      }
      const np = $('sNextPrize');
      if (d.phase === 'REVEAL' && d.prizeEveryQuestion && d.prizeMode && d.prizeMode !== 'none' && !d.roundDraws.length) {
        const left = Math.max(0, Math.ceil((d.revealedAt + d.autoDrawDelay * 1000 - now) / 1000));
        np.classList.remove('hidden'); np.textContent = `🎁 ${RQ.MODE[d.prizeMode].label} prize draw in ${left}s…`;
      } else np.classList.add('hidden');
    }
    if (d.phase === 'WINNER' && d.draw) {
      if (now < d.drawAt + d.drawAnimMs) {
        const m = RQ.MODE[d.draw.mode] || RQ.MODE.random_correct;
        $('sDrawBadge').textContent = `${m.emoji} ${m.label} prize`;
        $('sDrawTitle').textContent = d.draw.note === 'best_idea' ? 'And the best idea is…' : d.draw.mode === 'fastest' ? 'Who was the fastest?' : 'Spinning for a winner…';
        const c = d.draw.candidates.length ? d.draw.candidates : [{ name: '…', emoji: '❔' }];
        const p = c[Math.floor(now / 110) % c.length];
        $('sReelEmoji').textContent = p.emoji || '🎲';
        $('sReelName').textContent = p.name;
      } else if (!$('sDraw').classList.contains('hidden')) render();
    }
  }

  function wavePop(w) {
    const pop = document.createElement('div');
    pop.className = 'px-4 py-2 rounded-2xl font-bold text-[clamp(.85rem,1.2vw,1.4rem)] shadow-2xl border border-white/30 animate-float-pop flex items-center gap-2';
    pop.style.background = 'linear-gradient(135deg,#ff4fd8,#8b5cf6)';
    pop.innerHTML = `<span class="text-[1.4em]">${RQ.esc(w.emoji)}</span><span>${RQ.esc(w.name)} says hi! 👋</span>`;
    $('stageWaveOverlay').appendChild(pop);
    setTimeout(() => pop.remove(), 3000);
  }

  async function poll() {
    const r = await RQ.api('stage', { since: waveSince });
    if (r.ok) {
      RQ.syncClock(r.now);
      D = r;
      (r.waves || []).forEach(w => { waveSince = Math.max(waveSince, w.id); wavePop(w); });
      render();
      if (opts.onData) opts.onData(r);
    } else if (opts.onError) opts.onError(r);
    const fast = r.ok && ['QUESTION', 'REVEAL', 'WINNER'].includes(r.phase);
    pollTimer = setTimeout(poll, fast ? 600 : 1200);
  }

  function init(o = {}) {
    opts = o;
    setInterval(tick, 100);
    window.addEventListener('resize', () => { ringSize = 0; if (D && (D.phase === 'QUESTION' || D.phase === 'REVEAL')) ensureRing(); });
    poll();
  }
  function refreshNow() { clearTimeout(pollTimer); poll(); }

  return { init, refreshNow, get data() { return D; } };
})();
