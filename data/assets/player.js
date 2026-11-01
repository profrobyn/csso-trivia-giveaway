/* RizQuiz player (phone) logic. */
window.P = (function () {
  const $ = id => document.getElementById(id);
  const LS = {
    get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} },
  };
  let tokens = LS.get('rq_tokens', {});      // { eventId: token }
  let eventId = LS.get('rq_last_event', null);
  let S = null;                               // last state
  let selEmoji = null;
  let pendingChoice = null;                   // optimistic answer for current question
  let pendingKey = null;
  let pendingValue = null;                    // optimistic typed/ordered answer
  let showingCode = false;
  let kudosKey = null, kudosText = '';
  let celebrated = {};
  let emojis = [];
  let pollTimer = null;
  let failures = 0;

  const token = () => (eventId && tokens[eventId]) || '';
  const qKey = s => s.session.id + ':' + s.qIndex + ':' + s.startedAt;

  const CORRECT = n => [`Amazing! You must know everything!`, `Check out the big brain on ${n}!`, `Another example of ${n} being brilliant.`,
    `Pure genius, ${n}! Speed + accuracy on point!`, `Flex those trivia muscles, ${n}!`];
  const WRONG = n => [`So close — you've got the next one!`, `I hit the wrong key sometimes, too.`, `Shake it off ${n}, your comeback starts next round!`,
    `A temporary bump on your road to glory.`, `Keep your head up ${n}, speed round ahead!`];
  const pick = a => a[Math.floor(Math.random() * a.length)];

  function screens(id) {
    ['wWait', 'wReady', 'wQuestion', 'wIdeas', 'wLocked', 'wFeedback', 'wDrawing', 'wWin', 'wStand'].forEach(s => $(s).classList.toggle('hidden', s !== id));
  }
  function cards(id) {
    ['codeCard', 'closedCard', 'joinCard', 'codeNotice', 'gameCard'].forEach(c => $(c).classList.toggle('hidden', c !== id));
  }
  // per-game storage so one phone can play several hosts' games
  const key = k => 'rq_' + (RQ.game || '') + '_' + k;
  tokens = LS.get(key('tokens'), LS.get('rq_tokens', {}));
  eventId = LS.get(key('event'), null);

  function emojiGrid(el, current, onPick) {
    el.innerHTML = emojis.map(e => `<button type="button" class="emoji-btn ${e === current ? 'selected' : ''}" data-e="${e}">${e}</button>`).join('');
    el.querySelectorAll('button').forEach(b => b.onclick = () => {
      el.querySelectorAll('button').forEach(x => x.classList.remove('selected'));
      b.classList.add('selected'); onPick(b.dataset.e);
    });
  }

  function prizeLine(s) {
    const m = RQ.MODE[s.prizeMode];
    if (!s.announcePrize || !m || s.prizeMode === 'none') return '';
    return `🎁 Prize this round: ${RQT.prizeLabel(s.prizeMode, s.q)} — ${RQT.prizeRule(s.prizeMode, s.q)}`;
  }

  /* ---------------------------------------------------------------- render */
  function render() {
    const s = S; if (!s) return;
    $('appTitle').textContent = s.title;
    RQ.applyBrand(s.brand);
    document.title = s.title;
    if (!s.launched && !s.me) { cards('closedCard'); return; }
    if (!s.me) {
      if (!$('joinCard').classList.contains('hidden')) return;
      cards('joinCard');
      $('joinEvent').textContent = `${s.event.name} · ${s.session.label}`;
      $('joinWifi').classList.toggle('hidden', !s.showWifiWarning);
      $('joinWifiText').textContent = s.wifiText;
      if (!selEmoji) selEmoji = emojis[Math.floor(Math.random() * emojis.length)];
      $('nameEmoji').textContent = selEmoji;
      emojiGrid($('emojiGrid'), selEmoji, e => { selEmoji = e; $('nameEmoji').textContent = e; $('nameEmoji').classList.remove('animate-pop-in'); void $('nameEmoji').offsetWidth; $('nameEmoji').classList.add('animate-pop-in'); });
      return;
    }
    if (showingCode) { cards('codeNotice'); return; }
    cards('gameCard');
    const me = s.me;
    $('meEmoji').textContent = me.emoji; $('waitEmoji').textContent = me.emoji;
    $('meName').textContent = me.name;
    $('meCode').textContent = 'Rejoin code ' + me.code;
    $('meScore').textContent = RQ.fmt(me.score) + ' pts';
    $('meRank').textContent = rankText(s);
    $('waitDay').textContent = `📅 ${s.event.name} · ${s.session.label}`;
    if (!s.launched) { screens('wWait'); $('wWait').querySelector('h2').textContent = 'See you soon! 👋'; $('wWait').querySelector('p').textContent = 'The game is closed right now. This page will update when the host opens it again.'; return; }
    $('wWait').querySelector('h2').textContent = "You're locked in! 🚀";
    $('wWait').querySelector('p').textContent = 'Eyes on the big screen — the next question is coming.';

    const now = RQ.serverNow();
    switch (s.phase) {
      case 'LOBBY': screens('wWait'); break;
      case 'QUESTION': {
        const k = qKey(s);
        if (pendingKey !== k) { pendingKey = k; pendingChoice = null; pendingValue = null; }
        if (now < s.startedAt) { screens('wReady'); $('readyPrize').textContent = prizeLine(s); break; }
        const qLabel = s.adhoc ? '⭐ Bonus Question' : `Question ${s.qIndex + 1} of ${s.totalQ}`;
        if (s.q.type === 'ideas') {
          screens('wIdeas');
          $('ideaNum').textContent = qLabel + (s.q.ideaStage === 'vote' ? ' · Vote!' : ' · Share');
          $('ideaText').textContent = s.q.text;
          if (!$('ideaTimerBox').firstElementChild) $('ideaTimerBox').innerHTML = RQ.timerRing(64, 7);
          RQT.phoneIdeas($('ideaArea'), s.q, me.ideas, { submit: ideaSubmit, vote: ideaVote });
          break;
        }
        const sent = me.answer || pendingChoice !== null || pendingValue !== null;
        if (sent) {
          const ch = me.answer ? me.answer.choice : pendingChoice, val = me.answer ? me.answer.value : pendingValue;
          const L = RQT.phoneLocked(s.q, ch, val);
          screens('wLocked');
          $('lockedTile').className = `w-28 h-28 rounded-3xl grid place-items-center text-5xl animate-pop-in ${L.tile} !p-0 justify-center`;
          $('lockedTile').innerHTML = L.icon;
          $('lockedChoice').textContent = L.text + (s.q.scored ? ' Sit tight for the reveal!' : ' Watch the big screen!');
          break;
        }
        if ($('wQuestion').classList.contains('hidden') || $('qButtons').dataset.k !== k) {
          $('qButtons').dataset.k = k;
          $('qNum').textContent = qLabel;
          if (!$('qTimerBox').firstElementChild) $('qTimerBox').innerHTML = RQ.timerRing(64, 7);
          $('qText').textContent = s.q.text;
          const pl = prizeLine(s);
          $('qPrize').textContent = pl; $('qPrize').classList.toggle('hidden', !pl);
          RQT.phoneInput($('qButtons'), s.q, (choice, value) => answer(choice, value), s.roundId);
        }
        screens('wQuestion');
        break;
      }
      case 'REVEAL': case 'LEADERBOARD':
        if (s.phase === 'LEADERBOARD' || s.qIndex < 0) { standing(s, false); break; }
        feedback(s); break;
      case 'WINNER':
        if (!s.draw) { standing(s, false); break; }
        if (now < s.drawAt + s.drawAnimMs) {
          screens('wDrawing');
          $('drawingMode').textContent = (RQ.MODE[s.draw.mode] || {}).label + ' prize';
        } else winner(s);
        break;
      case 'PODIUM': standing(s, true); break;
    }
  }

  function rankText(s) {
    const me = s.me;
    if (s.locked) return '🔒 Rank hidden for the finale';
    if (!me.rank || !me.score) return 'Unranked';
    return `${RQ.ordinal(me.rank)} of ${me.players}` + (s.scope === 'session' ? ' today' : '');
  }

  function feedback(s) {
    screens('wFeedback');
    const me = s.me, a = me.answer;
    const k = qKey(s);
    const correct = a && a.correct;
    const opinion = s.q && s.q.scored === false;
    if (opinion) {
      if (kudosKey !== k) { kudosKey = k; if (a && typeof confetti === 'function') confetti({ particleCount: 40, spread: 60 }); }
      $('fbIconBox').className = 'w-24 h-24 rounded-full grid place-items-center text-5xl animate-pop-in bg-gradient-to-br from-violet-500 to-pink-500 shadow-[0_0_50px_rgba(139,92,246,.6)]';
      $('fbIcon').className = a ? 'fa-solid fa-heart' : 'fa-solid fa-hourglass-end';
      $('fbTitle').textContent = a ? 'Thanks for sharing! 🙌' : 'Round over!'; $('fbTitle').className = 'font-display text-3xl font-bold text-pink-200';
      $('fbPoints').textContent = a && a.points ? `+${RQ.fmt(a.points)} pts` + (s.q.type === 'ideas' && s.q.bestPts && a.points >= s.q.bestPts ? ' — your idea won! 🏆' : '') : '';
      $('fbKudos').textContent = 'See the results on the big screen 👀';
    } else if (kudosKey !== k) {
      kudosKey = k;
      kudosText = pick(correct ? CORRECT(me.name) : WRONG(me.name));
      if (correct && typeof confetti === 'function') confetti({ particleCount: 60, spread: 60 });
    }
    if (opinion) { /* handled above */ } else if (correct) {
      $('fbIconBox').className = 'w-24 h-24 rounded-full grid place-items-center text-5xl animate-pop-in bg-gradient-to-br from-emerald-400 to-cyan-500 shadow-[0_0_50px_rgba(16,185,129,.6)]';
      $('fbIcon').className = 'fa-solid fa-check';
      $('fbTitle').textContent = 'Correct! 🎉'; $('fbTitle').className = 'font-display text-3xl font-bold text-emerald-300';
      $('fbPoints').textContent = `+${RQ.fmt(a.points)} pts (${(a.ms / 1000).toFixed(2)}s)`;
    } else {
      $('fbIconBox').className = 'w-24 h-24 rounded-full grid place-items-center text-5xl animate-pop-in bg-gradient-to-br from-rose-500 to-pink-600 shadow-[0_0_50px_rgba(244,63,94,.5)]';
      $('fbIcon').className = a ? 'fa-solid fa-xmark' : 'fa-solid fa-hourglass-end';
      $('fbTitle').textContent = a ? 'Not quite!' : "Time's up!"; $('fbTitle').className = 'font-display text-3xl font-bold text-rose-300';
      $('fbPoints').textContent = s.q ? 'Answer: ' + RQT.correctText(s.q) : '';
    }
    if (!opinion) $('fbKudos').textContent = kudosText;
    const note = (s.q && s.q.note) || '';
    $('fbNote').textContent = note ? '💬 ' + note : ''; $('fbNote').classList.toggle('hidden', !note);
    $('fbScoreLbl').textContent = s.scope === 'session' ? 'Today' : 'Event total';
    $('fbTotal').textContent = RQ.fmt(me.score) + ' pts';
    $('fbRank').textContent = s.locked ? '🔒 Hidden' : (me.rank && me.score ? `${RQ.ordinal(me.rank)} of ${me.players}` : '--');
    let pz = '';
    if (s.prizeEveryQuestion && s.prizeMode && s.prizeMode !== 'none') {
      const label = RQT.prizeLabel(s.prizeMode, s.q);
      const eligible = s.prizeMode === 'random_player' || correct;
      pz = eligible ? `🎟️ You're in the ${label.toLowerCase()} prize draw!` : `🎁 ${label} prize draw coming up…`;
      if (s.prizeMode === 'fastest' && correct && s.q.scored) pz = `⚡ Fastest correct answer wins — fingers crossed!`;
      if (s.prizeMode === 'fastest' && s.q.type === 'ideas') pz = '💡 The best idea wins the prize!';
    }
    $('fbPrize').textContent = pz; $('fbPrize').classList.toggle('hidden', !pz);
  }

  function winner(s) {
    screens('wWin');
    const d = s.draw, me = s.me;
    const mine = d.playerId === me.id;
    if (mine) {
      $('wWin').className = 'flex-1 flex flex-col items-center justify-center text-center gap-3 p-6 rounded-[1.4rem] ring-glow';
      $('wWin').style.background = 'linear-gradient(160deg,#f59e0b,#ec4899 55%,#7c3aed)';
      $('winEmoji').textContent = '🏆';
      $('winTitle').textContent = '🎉 YOU WON A PRIZE!';
      $('winSub').textContent = `Congratulations ${me.name}! See the host to collect your prize.`;
      if (!celebrated[d.id]) { celebrated[d.id] = 1; if (typeof confetti === 'function') { confetti({ particleCount: 150, spread: 80 }); setTimeout(() => confetti({ particleCount: 100, spread: 100 }), 700); } if (navigator.vibrate) navigator.vibrate([200, 100, 200]); }
    } else {
      $('wWin').className = 'flex-1 flex flex-col items-center justify-center text-center gap-3 p-6 glass';
      $('wWin').style.background = '';
      $('winEmoji').textContent = d.name ? (d.emoji || '👑') : '🤷';
      $('winTitle').textContent = d.name ? `${d.name} wins!` : 'No eligible winner this time';
      $('winSub').textContent = (RQ.MODE[d.mode] || {}).label + ' prize' + (d.mode === 'fastest' && d.ms !== null ? ` · ${(d.ms / 1000).toFixed(2)}s` : '') + ' — keep playing for the next one!';
    }
  }

  function standing(s, final) {
    screens('wStand');
    const me = s.me;
    const medals = ['🥇', '🥈', '🥉'];
    $('standEmoji').textContent = final && me.rank && me.rank <= 3 ? medals[me.rank - 1] : me.emoji;
    $('standTitle').textContent = final ? (me.rank === 1 ? 'CHAMPION! 👑' : me.rank && me.rank <= 3 ? 'You made the podium!' : 'Final Standings') : 'Leaderboard';
    $('standSub').textContent = `${RQ.fmt(me.score)} pts · ${s.locked ? 'rank hidden' : (me.rank && me.score ? RQ.ordinal(me.rank) + ' of ' + me.players : 'unranked')}`;
    $('standWins').textContent = me.wins ? `🎁 You've won ${me.wins} prize${me.wins > 1 ? 's' : ''} in this event!` : '';
    $('standWins').classList.toggle('hidden', !me.wins);
    const cta = $('signupCta');
    cta.classList.toggle('hidden', !(final && s.signupUrl));
    if (final && s.signupUrl && cta.getAttribute('href') !== s.signupUrl) cta.setAttribute('href', s.signupUrl);
    if (final && me.rank && me.rank <= 3 && !celebrated['podium' + s.version]) {
      celebrated['podium' + s.version] = 1; if (typeof confetti === 'function') confetti({ particleCount: 160, spread: 90 });
    }
  }

  /* ------------------------------------------------------------- ticker */
  setInterval(() => {
    if (!S || !S.me) return;
    const now = RQ.serverNow();
    if (S.phase === 'QUESTION') {
      if (now < S.startedAt) { const n = String(Math.ceil((S.startedAt - now) / 1000)); if ($('readyNum').textContent !== n) $('readyNum').textContent = n; }
      else {
        if (!$('wReady').classList.contains('hidden')) render();
        const left = Math.max(0, (S.startedAt + S.timeLimit * 1000 - now) / 1000);
        RQ.setRing($('qTimerBox').firstElementChild, left, S.timeLimit);
        RQ.setRing($('ideaTimerBox').firstElementChild, left, S.timeLimit);
      }
    }
    if (S.phase === 'WINNER' && S.draw && now >= S.drawAt + S.drawAnimMs && !$('wDrawing').classList.contains('hidden')) render();
  }, 100);

  /* ---------------------------------------------------------------- poll */
  async function poll() {
    const sent = token();
    const r = await RQ.api('state', { token: sent });
    if (r.ok) {
      failures = 0;
      $('connDot').innerHTML = '<span class="live-dot"></span> live';
      $('connDot').className = 'chip chip-green !text-[11px]';
      RQ.syncClock(r.now);
      emojis = r.emojis || emojis;
      const evChanged = String(r.event.id) !== String(eventId);
      eventId = r.event.id; LS.set(key('event'), eventId);
      if (evChanged && tokens[eventId] && tokens[eventId] !== sent) { schedule(50); return; }
      if (!r.me && sent && !evChanged) { delete tokens[eventId]; LS.set(key('tokens'), tokens); }
      S = r;
      render();
    } else {
      failures++;
      if (failures > 2) {
        $('connDot').innerHTML = '<span class="w-2 h-2 rounded-full bg-rose-500"></span> reconnecting';
        $('connDot').className = 'chip chip-red !text-[11px]';
      }
      if (r.code === 'NO_GAME') { cards('codeCard'); $('codeErr').textContent = r.error; $('codeErr').classList.remove('hidden'); return; }
    }
    const fast = S && ['QUESTION', 'REVEAL', 'WINNER'].includes(S.phase);
    schedule(fast ? 800 : 1500);
  }
  function schedule(ms) { clearTimeout(pollTimer); pollTimer = setTimeout(poll, ms); }

  /* ------------------------------------------------------------- actions */
  function saveToken(t) { tokens[eventId] = t; LS.set(key('tokens'), tokens); }

  async function join() {
    const name = $('nameInput').value.trim();
    if (!name) { RQ.toast('Please enter your name first!', 'error'); $('nameInput').focus(); return; }
    $('joinBtn').disabled = true;
    const r = await RQ.api('join', { name, emoji: selEmoji || '' });
    $('joinBtn').disabled = false;
    if (r.ok) {
      saveToken(r.token);
      $('codeBig').textContent = r.code;
      $('welcomeEmoji').textContent = selEmoji || '🎉';
      if (typeof confetti === 'function') confetti({ particleCount: 90, spread: 70, origin: { y: .4 } });
      showingCode = true;
      $('takenBox').classList.add('hidden');
      cards('codeNotice');
      schedule(10);
    } else if (r.code === 'NAME_TAKEN') {
      $('takenMsg').textContent = `"${name}" is already taken in this event.`;
      $('takenBox').classList.remove('hidden');
      $('nameInput').select();
    } else RQ.toast(r.error, 'error');
  }

  async function rejoin() {
    const name = $('nameInput').value.trim(), code = $('codeInput').value.trim();
    if (!name || !code) { RQ.toast('Enter your name and rejoin code.', 'error'); return; }
    const r = await RQ.api('rejoin', { name, code });
    if (r.ok) { saveToken(r.token); $('takenBox').classList.add('hidden'); RQ.toast('Welcome back! 👋', 'ok'); cards('gameCard'); schedule(10); }
    else RQ.toast(r.error, 'error');
  }

  function ackCode() { showingCode = false; render(); }

  async function answer(choice, value = null) {
    if (!S || S.phase !== 'QUESTION') return;
    pendingChoice = choice; pendingValue = value; pendingKey = qKey(S);
    render();
    const payload = { token: token(), sessionId: S.session.id, qIndex: S.qIndex, roundId: S.roundId };
    if (choice !== null && choice !== undefined) payload.choice = choice;
    if (value !== null && value !== undefined) payload.value = JSON.stringify(value);
    const r = await RQ.api('answer', payload);
    if (!r.ok) {
      RQ.toast(r.error, 'error');
      if (!/closed|time is up/i.test(r.error || '')) { pendingChoice = null; pendingValue = null; $('qButtons').dataset.k = ''; render(); }
    }
    schedule(200);
  }
  async function ideaSubmit(text) {
    const r = await RQ.api('idea_submit', { token: token(), roundId: S.roundId, text });
    if (r.ok) { RQ.toast('Idea shared! 💡', 'ok'); $('ideaArea').dataset.sig = ''; } else RQ.toast(r.error, 'error');
    schedule(100);
  }
  async function ideaVote(ideaId, vote) {
    const r = await RQ.api('idea_vote', { token: token(), roundId: S.roundId, ideaId, vote });
    if (!r.ok) RQ.toast(r.error, 'error');
    schedule(100);
  }

  async function wave() {
    const r = await RQ.api('wave', { token: token() });
    RQ.toast(r.ok ? 'Your name popped up on the big screen! 👋' : r.error, r.ok ? 'ok' : 'error');
  }

  function openMe() {
    if (!S || !S.me) return;
    $('modalCode').textContent = S.me.code;
    emojiGrid($('modalEmojiGrid'), S.me.emoji, async e => {
      const r = await RQ.api('set_emoji', { token: token(), emoji: e });
      if (r.ok) { S.me.emoji = e; render(); RQ.toast('Emoji updated ' + e, 'ok'); }
    });
    $('meModal').classList.remove('hidden');
  }
  function closeMe() { $('meModal').classList.add('hidden'); }

  async function enterCode() {
    const g = $('codeEntry').value.trim();
    if (!g) return;
    const r = await RQ.api('find_game', { g });
    if (r.ok) location.href = encodeURIComponent(r.g) + '/';
    else { $('codeErr').textContent = r.error; $('codeErr').classList.remove('hidden'); }
  }

  document.addEventListener('DOMContentLoaded', () => {
    $('nameInput').addEventListener('keydown', e => { if (e.key === 'Enter') join(); });
    $('nameInput').addEventListener('input', () => $('takenBox').classList.add('hidden'));
    $('codeEntry').addEventListener('keydown', e => { if (e.key === 'Enter') enterCode(); });
    $('meModal').addEventListener('click', e => { if (e.target.id === 'meModal') closeMe(); });
    if (!RQ.game) { cards('codeCard'); $('connDot').classList.add('hidden'); setTimeout(() => $('codeEntry').focus(), 100); return; }
    poll();
  });

  return { join, rejoin, ackCode, answer, wave, openMe, closeMe, enterCode };
})();
