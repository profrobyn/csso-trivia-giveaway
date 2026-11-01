/* RizQuiz question types: phone inputs and big-screen visuals. Used by player.js and stage.js. */
window.RQT = (function () {
  const esc = s => RQ.esc(s);
  const fmtNum = v => (Math.round(v * 100) / 100).toLocaleString();

  const INFO = {
    mc: { icon: '🔤', label: 'Multiple choice', hint: 'Tap the right answer — fast!' },
    tf: { icon: '✅', label: 'True or false', hint: 'True or false? Tap fast!' },
    typeans: { icon: '⌨️', label: 'Type the answer', hint: 'Type your answer on your phone' },
    estimate: { icon: '🎯', label: 'Closest number', hint: 'Slide or type your best guess' },
    ranking: { icon: '🔢', label: 'Put in order', hint: 'Tap the items in the right order' },
    poll: { icon: '📊', label: 'Poll', hint: 'No wrong answers — just vote!' },
    rating: { icon: '⭐', label: 'Rating', hint: 'Rate it on your phone' },
    wordcloud: { icon: '☁️', label: 'Word cloud', hint: 'Send a word or two — watch the cloud grow!' },
    ideas: { icon: '💡', label: 'Idea board', hint: 'Share your ideas, then vote for the best one' },
  };

  /** Prize names depend on the question: opinion questions have no "correct" answers. */
  function prizeLabel(mode, q) {
    const m = RQ.MODE[mode]; if (!m) return '';
    if (q && q.scored === false) {
      if (mode === 'random_correct') return 'Random participant';
      if (mode === 'fastest') return q.type === 'ideas' ? 'Best idea' : 'Fastest response';
    }
    return m.label;
  }
  function prizeRule(mode, q) {
    if (q && q.scored === false) {
      if (mode === 'random_correct') return 'Everyone who takes part is in the draw!';
      if (mode === 'fastest') return q.type === 'ideas' ? 'The idea with the most ⭐ nominations wins!' : 'The first response wins!';
    }
    return (RQ.MODE[mode] || {}).rule || '';
  }

  /* ================================================================ PHONE */

  /** Build the answer controls for a question. onAnswer(choice, value). */
  function phoneInput(el, q, onAnswer, roundId) {
    const t = q.type;
    if (t === 'mc' || t === 'tf' || t === 'poll') {
      const n = q.options.length;
      el.className = `flex-1 grid gap-2.5 min-h-[46vh] ${n <= 2 ? 'grid-cols-1' : 'grid-cols-2'}`;
      el.innerHTML = q.options.map((o, i) => `
        <button data-i="${i}" class="ans ans-${i} rise-in !flex-col !items-start !justify-between !gap-2 text-[1.05rem]" style="animation-delay:${i * 60}ms">
          ${RQ.shape(i)}<span class="txt w-full">${esc(o)}</span></button>`).join('');
      el.querySelectorAll('button').forEach(b => b.onclick = () => onAnswer(+b.dataset.i, null));
      return;
    }
    el.className = 'flex-1 flex flex-col gap-3';
    if (t === 'rating') {
      const nums = Array.from({ length: q.scale }, (_, i) => i + 1);
      el.innerHTML = `<div class="grid ${q.scale === 5 ? 'grid-cols-5' : 'grid-cols-5'} gap-2">${nums.map(n => `
        <button data-n="${n}" class="ans ans-${(n - 1) % 6} !p-0 aspect-square !justify-center font-display text-3xl rise-in" style="animation-delay:${n * 40}ms">${n}</button>`).join('')}</div>
        <div class="flex justify-between text-sm text-muted font-semibold"><span>1 · ${esc(q.lowLabel)}</span><span>${q.scale} · ${esc(q.highLabel)}</span></div>`;
      el.querySelectorAll('button').forEach(b => b.onclick = () => onAnswer(+b.dataset.n, null));
    } else if (t === 'typeans') {
      el.innerHTML = `<input class="field !text-2xl !py-4 text-center font-display" maxlength="80" placeholder="Type your answer…" autocomplete="off" autocapitalize="sentences">
        <button class="btn btn-primary btn-xl w-full">Send answer <i class="fa-solid fa-paper-plane"></i></button>`;
      const inp = el.querySelector('input');
      const go = () => { if (inp.value.trim()) onAnswer(null, inp.value.trim()); else RQ.toast('Type an answer first.', 'error'); };
      el.querySelector('button').onclick = go; inp.onkeydown = e => { if (e.key === 'Enter') go(); };
      setTimeout(() => inp.focus(), 50);
    } else if (t === 'estimate') {
      const mid = Math.round(((q.min + q.max) / 2) / q.step) * q.step;
      el.innerHTML = `<div class="glass p-5 text-center"><p class="font-display text-5xl font-bold text-amber-200"><span id="estShow">${fmtNum(mid)}</span>
          <span class="text-2xl text-muted">${esc(q.unit || '')}</span></p></div>
        <input type="range" min="${q.min}" max="${q.max}" step="${q.step}" value="${mid}" class="w-full accent-pink-500 h-10">
        <div class="flex justify-between text-sm text-muted font-bold"><span>${fmtNum(q.min)}</span><span>${fmtNum(q.max)}</span></div>
        <input type="number" inputmode="decimal" class="field text-center !text-lg" placeholder="…or type a number" step="${q.step}" min="${q.min}" max="${q.max}">
        <button class="btn btn-primary btn-xl w-full">Lock in my guess <i class="fa-solid fa-bullseye"></i></button>`;
      const rng = el.querySelector('input[type=range]'), num = el.querySelector('input[type=number]'), show = el.querySelector('#estShow');
      rng.oninput = () => { show.textContent = fmtNum(+rng.value); num.value = ''; };
      num.oninput = () => { if (num.value !== '') { show.textContent = fmtNum(+num.value); rng.value = num.value; } };
      el.querySelector('button').onclick = () => {
        const v = num.value !== '' ? +num.value : +rng.value;
        if (v < q.min || v > q.max) { RQ.toast(`Pick a number from ${fmtNum(q.min)} to ${fmtNum(q.max)}.`, 'error'); return; }
        onAnswer(null, v);
      };
    } else if (t === 'ranking') {
      let picked = [];
      const draw = () => {
        el.innerHTML = `<p class="text-sm text-muted font-semibold">Tap in order: <b class="text-white">first</b> at the top. Tap again to undo.</p>
          <div class="flex flex-col gap-2">${q.items.map((it, k) => {
            const pos = picked.indexOf(it.id);
            return `<button data-id="${it.id}" class="ans ans-${k % 6} !py-3 ${pos >= 0 ? '' : 'opacity-90'}" style="${pos >= 0 ? 'filter:saturate(1.2)' : 'filter:grayscale(.35)'}">
              <span class="shape font-display !text-xl">${pos >= 0 ? pos + 1 : '·'}</span><span class="txt">${esc(it.text)}</span></button>`;
          }).join('')}</div>
          <div class="flex gap-2"><button class="btn flex-1" data-act="reset"><i class="fa-solid fa-rotate-left"></i> Start over</button>
            <button class="btn btn-primary flex-[2]" data-act="go" ${picked.length === q.items.length ? '' : 'disabled'}>Submit order <i class="fa-solid fa-check"></i></button></div>`;
        el.querySelectorAll('[data-id]').forEach(b => b.onclick = () => {
          const id = +b.dataset.id, p = picked.indexOf(id);
          if (p >= 0) picked = picked.slice(0, p); else picked.push(id);
          draw();
        });
        el.querySelector('[data-act=reset]').onclick = () => { picked = []; draw(); };
        el.querySelector('[data-act=go]').onclick = () => onAnswer(null, picked);
      };
      draw();
    } else if (t === 'wordcloud') {
      el.innerHTML = Array.from({ length: q.maxEntries }, (_, i) => `
        <input class="field !text-xl !py-3.5 text-center font-display" maxlength="25" placeholder="${i === 0 ? 'Your word…' : 'Another word (optional)'}" autocomplete="off">`).join('') +
        `<button class="btn btn-primary btn-xl w-full">Send to the cloud <i class="fa-solid fa-cloud-arrow-up"></i></button>`;
      const inputs = [...el.querySelectorAll('input')];
      const go = () => { const w = inputs.map(i => i.value.trim()).filter(Boolean); if (w.length) onAnswer(null, w); else RQ.toast('Type at least one word.', 'error'); };
      el.querySelector('button').onclick = go;
      inputs.forEach(i => i.onkeydown = e => { if (e.key === 'Enter') go(); });
      setTimeout(() => inputs[0].focus(), 50);
    }
  }

  /** "Locked in" summary of what the player sent. */
  function phoneLocked(q, choice, value) {
    const t = q.type;
    if (t === 'mc' || t === 'tf' || t === 'poll') return { tile: `ans ans-${choice}`, icon: '<i class="fa-solid fa-lock"></i>', text: `You picked “${q.options[choice]}”.` };
    if (t === 'rating') return { tile: `ans ans-${(choice - 1) % 6}`, icon: `<span class="font-display">${choice}</span>`, text: `You rated it ${choice} of ${q.scale}.` };
    if (t === 'typeans') return { tile: 'ans ans-1', icon: '⌨️', text: `You answered “${value}”.` };
    if (t === 'estimate') return { tile: 'ans ans-2', icon: '🎯', text: `Your guess: ${fmtNum(value)} ${q.unit || ''}` };
    if (t === 'ranking') return { tile: 'ans ans-3', icon: '🔢', text: 'Your order: ' + (value || []).map(id => (q.items.find(x => x.id === id) || {}).text).join(' → ') };
    if (t === 'wordcloud') return { tile: 'ans ans-5', icon: '☁️', text: 'You sent: ' + (value || []).join(', ') };
    return { tile: 'ans ans-0', icon: '✔', text: '' };
  }

  /** The correct answer, in words (for the reveal on phones). */
  function correctText(q) {
    switch (q.type) {
      case 'mc': case 'tf': return q.correctIndex !== undefined ? q.options[q.correctIndex] : '';
      case 'typeans': return (q.accepted || [])[0] || '';
      case 'estimate': return q.answer !== undefined ? `${fmtNum(q.answer)} ${q.unit || ''}${q.tolerance ? ` (±${fmtNum(q.tolerance)})` : ''}` : '';
      case 'ranking': return (q.correctOrder || []).join(' → ');
    }
    return '';
  }

  /** Idea board on the phone. ctx: {s, me, submit(text), vote(id, v)} */
  function phoneIdeas(el, q, ideas, api, now, startedAt) {
    const stage = q.ideaStage;
    const pts = q.bestPts ? `<p class="chip chip-gold !whitespace-normal">🏅 Best idea earns ${RQ.fmt(q.bestPts)} pts${q.voterPts ? ` · voting earns ${RQ.fmt(q.voterPts)} pts` : ''}</p>` : '';
    if (stage === 'submit') {
      const mine = (ideas && ideas.mine) || [];
      const left = q.maxIdeas - mine.length;
      const sig = 'S' + mine.map(i => i.id).join(',');
      if (el.dataset.sig === sig) return;
      el.dataset.sig = sig;
      el.innerHTML = `${pts}
        ${left > 0 ? `<textarea class="field !text-lg" rows="3" maxlength="140" placeholder="Type your idea…"></textarea>
          <button class="btn btn-primary btn-xl w-full">Share idea <i class="fa-solid fa-lightbulb"></i></button>
          <p class="text-sm text-muted text-center">You can share ${left} more idea${left === 1 ? '' : 's'}.</p>`
        : `<div class="glass-2 p-4 text-center"><div class="text-4xl">🙌</div><p class="font-bold mt-1">Thanks! Voting starts soon.</p></div>`}
        ${mine.length ? `<p class="lbl !mb-0 mt-1">Your ideas</p>` + mine.map(i => `<div class="glass-2 p-3 font-semibold">💡 ${esc(i.text)}</div>`).join('') : ''}`;
      const ta = el.querySelector('textarea');
      if (ta) {
        el.querySelector('.btn-primary').onclick = () => { if (ta.value.trim()) api.submit(ta.value.trim()); };
        setTimeout(() => ta.focus(), 50);
      }
      return;
    }
    // voting
    const board = (ideas && ideas.board) || [];
    const sig = 'V' + JSON.stringify(board);
    if (el.dataset.sig === sig) return;
    el.dataset.sig = sig;
    const rule = `⭐ Nominate the best idea (${q.votesPerPlayer} nomination${q.votesPerPlayer > 1 ? 's' : ''})` +
      (q.removeAfter ? ` · 👎 ${q.removeAfter} thumbs-down removes an idea` : ' · 👎 thumbs-down ideas that miss');
    el.innerHTML = `${pts}<p class="text-sm text-muted font-semibold">${rule}</p>` + board.map(i => `
      <div class="glass-2 p-3 flex items-center gap-2 ${i.removed ? 'opacity-40' : ''}">
        <p class="flex-1 font-semibold ${i.removed ? 'line-through' : ''}">${esc(i.text)}${i.mine ? ' <span class="chip chip-cyan !text-[10px] !py-0">yours</span>' : ''}${i.removed ? ' <span class="chip chip-red !text-[10px] !py-0">removed</span>' : ''}</p>
        ${i.mine || i.removed ? `<span class="text-sm text-muted">⭐ ${i.up}</span>` : `
          <button data-id="${i.id}" data-v="${i.myVote === 1 ? 0 : 1}" class="btn btn-sm ${i.myVote === 1 ? 'btn-gold' : ''}" title="Nominate">⭐ ${i.up}</button>
          <button data-id="${i.id}" data-v="${i.myVote === -1 ? 0 : -1}" class="btn btn-sm ${i.myVote === -1 ? 'btn-danger' : ''}" title="Thumbs down">👎</button>`}
      </div>`).join('');
    el.querySelectorAll('button[data-id]').forEach(b => b.onclick = () => api.vote(+b.dataset.id, +b.dataset.v));
  }

  /* ================================================================ STAGE */

  function bar(label, n, total, color) {
    const pct = total ? Math.round(n / total * 100) : 0;
    return `<div class="flex items-center gap-3">
      <span class="w-[30%] min-w-0 truncate font-semibold text-[clamp(.9rem,1.6vw,2rem)]">${label}</span>
      <div class="flex-1 h-[clamp(1.4rem,3.6vh,3.2rem)] rounded-xl overflow-hidden" style="background:rgba(255,255,255,.08)">
        <div class="h-full rounded-xl transition-all duration-700" style="width:${n ? Math.max(pct, 3) : 0}%;background:${color}"></div></div>
      <span class="w-[5.5em] text-right font-display font-bold text-[clamp(.9rem,1.6vw,2rem)]">${n} <span class="text-muted text-[.7em]">${pct}%</span></span></div>`;
  }

  /** Word cloud: biggest words in the middle, colors cycling. */
  function cloudHtml(words) {
    if (!words || !words.length) return `<div class="h-full grid place-items-center text-muted text-[clamp(1.2rem,2.4vw,3rem)] bob">☁️ Waiting for words…</div>`;
    const max = words[0].n, colors = ['#fde047', '#ff4fd8', '#22d3ee', '#a3e635', '#fb923c', '#c084fc', '#60a5fa', '#f472b6'];
    const arranged = [];
    words.forEach((w, i) => i % 2 ? arranged.push(w) : arranged.unshift(w));
    return `<div class="h-full flex flex-wrap items-center justify-center content-center gap-x-[clamp(.6rem,1.6vw,2rem)] gap-y-1 px-4">` + arranged.map((w, i) => {
      const size = 1 + 3.6 * Math.sqrt(w.n / max);
      const rot = (w.w.length + i) % 7 === 0 ? -6 : (w.w.length + i) % 5 === 0 ? 5 : 0;
      return `<span class="font-display font-bold leading-none animate-pop-in" style="font-size:clamp(${(size * .55).toFixed(2)}rem, ${(size * 1.15).toFixed(2)}vw, ${(size * 1.6).toFixed(2)}rem);color:${colors[(w.w.charCodeAt(0) + i) % colors.length]};transform:rotate(${rot}deg)" title="${w.n}">${esc(w.w)}</span>`;
    }).join('') + `</div>`;
  }

  /**
   * Big-screen area for non multiple-choice questions.
   * Returns true when it handled the question (then the classic option grid is hidden).
   */
  function stageArea(el, d) {
    const q = d.q, t = q.type, live = d.live || {}, revealed = d.phase !== 'QUESTION';
    if (t === 'mc' || t === 'tf') return false;
    let html = '';
    if (t === 'poll') {
      const total = (live.counts || []).reduce((a, b) => a + b, 0);
      html = `<div class="h-full flex flex-col justify-center gap-[clamp(.5rem,1.6vh,1.4rem)] max-w-[1400px] w-full mx-auto">` +
        q.options.map((o, i) => bar(esc(o), (live.counts || [])[i] || 0, total, `var(--a${i % 6})`)).join('') + `</div>`;
    } else if (t === 'rating') {
      const total = (live.counts || []).reduce((a, b) => a + b, 0);
      html = `<div class="h-full grid gap-[clamp(1rem,3vw,4rem)] items-center" style="grid-template-columns:minmax(0,1fr) minmax(0,2fr)">
        <div class="text-center"><p class="font-display font-bold text-[clamp(4rem,10vw,12rem)] leading-none text-amber-200">${live.avg ?? '–'}</p>
          <p class="text-muted font-bold text-[clamp(.9rem,1.4vw,1.8rem)]">average out of ${q.scale} · ${live.n || 0} vote${live.n === 1 ? '' : 's'}</p>
          <p class="text-[clamp(1.6rem,3vw,3.6rem)] mt-2 text-amber-300">${'★'.repeat(Math.round(live.avg || 0)).padEnd(q.scale, '☆')}</p></div>
        <div class="flex flex-col gap-[clamp(.3rem,1vh,.8rem)]">${(live.counts || []).map((n, i) => bar(`${i + 1}${i === 0 ? ' · ' + esc(q.lowLabel) : i === q.scale - 1 ? ' · ' + esc(q.highLabel) : ''}`, n, total, 'linear-gradient(90deg,#fde047,#f59e0b)')).join('')}</div></div>`;
    } else if (t === 'wordcloud') {
      el.innerHTML = Array.from({ length: q.maxEntries }, (_, i) => `
        <input class="field !text-xl !py-3.5 text-center font-display" maxlength="25" placeholder="${i === 0 ? 'Your word…' : 'Another word (optional)'}" autocomplete="off">`).join('') +
        `<button class="btn btn-primary btn-xl w-full">Send to the cloud <i class="fa-solid fa-cloud-arrow-up"></i></button>`;
      const inputs = [...el.querySelectorAll('input')];
      const go = () => { const w = inputs.map(i => i.value.trim()).filter(Boolean); if (w.length) onAnswer(null, w); else RQ.toast('Type at least one word.', 'error'); };
      el.querySelector('button').onclick = go;
      inputs.forEach(i => i.onkeydown = e => { if (e.key === 'Enter') go(); });
      setTimeout(() => inputs[0].focus(), 50);
    }
  }

  /** "Locked in" summary of what the player sent. */
  function phoneLocked(q, choice, value) {
    const t = q.type;
    if (t === 'mc' || t === 'tf' || t === 'poll') return { tile: `ans ans-${choice}`, icon: '<i class="fa-solid fa-lock"></i>', text: `You picked “${q.options[choice]}”.` };
    if (t === 'rating') return { tile: `ans ans-${(choice - 1) % 6}`, icon: `<span class="font-display">${choice}</span>`, text: `You rated it ${choice} of ${q.scale}.` };
    if (t === 'typeans') return { tile: 'ans ans-1', icon: '⌨️', text: `You answered “${value}”.` };
    if (t === 'estimate') return { tile: 'ans ans-2', icon: '🎯', text: `Your guess: ${fmtNum(value)} ${q.unit || ''}` };
    if (t === 'ranking') return { tile: 'ans ans-3', icon: '🔢', text: 'Your order: ' + (value || []).map(id => (q.items.find(x => x.id === id) || {}).text).join(' → ') };
    if (t === 'wordcloud') return { tile: 'ans ans-5', icon: '☁️', text: 'You sent: ' + (value || []).join(', ') };
    return { tile: 'ans ans-0', icon: '✔', text: '' };
  }

  /** The correct answer, in words (for the reveal on phones). */
  function correctText(q) {
    switch (q.type) {
      case 'mc': case 'tf': return q.correctIndex !== undefined ? q.options[q.correctIndex] : '';
      case 'typeans': return (q.accepted || [])[0] || '';
      case 'estimate': return q.answer !== undefined ? `${fmtNum(q.answer)} ${q.unit || ''}${q.tolerance ? ` (±${fmtNum(q.tolerance)})` : ''}` : '';
      case 'ranking': return (q.correctOrder || []).join(' → ');
    }
    return '';
  }

  /** Idea board on the phone. ctx: {s, me, submit(text), vote(id, v)} */
  function phoneIdeas(el, q, ideas, api, now, startedAt) {
    const stage = q.ideaStage;
    const pts = q.bestPts ? `<p class="chip chip-gold !whitespace-normal">🏅 Best idea earns ${RQ.fmt(q.bestPts)} pts${q.voterPts ? ` · voting earns ${RQ.fmt(q.voterPts)} pts` : ''}</p>` : '';
    if (stage === 'submit') {
      const mine = (ideas && ideas.mine) || [];
      const left = q.maxIdeas - mine.length;
      const sig = 'S' + mine.map(i => i.id).join(',');
      if (el.dataset.sig === sig) return;
      el.dataset.sig = sig;
      el.innerHTML = `${pts}
        ${left > 0 ? `<textarea class="field !text-lg" rows="3" maxlength="140" placeholder="Type your idea…"></textarea>
          <button class="btn btn-primary btn-xl w-full">Share idea <i class="fa-solid fa-lightbulb"></i></button>
          <p class="text-sm text-muted text-center">You can share ${left} more idea${left === 1 ? '' : 's'}.</p>`
        : `<div class="glass-2 p-4 text-center"><div class="text-4xl">🙌</div><p class="font-bold mt-1">Thanks! Voting starts soon.</p></div>`}
        ${mine.length ? `<p class="lbl !mb-0 mt-1">Your ideas</p>` + mine.map(i => `<div class="glass-2 p-3 font-semibold">💡 ${esc(i.text)}</div>`).join('') : ''}`;
      const ta = el.querySelector('textarea');
      if (ta) {
        el.querySelector('.btn-primary').onclick = () => { if (ta.value.trim()) api.submit(ta.value.trim()); };
        setTimeout(() => ta.focus(), 50);
      }
      return;
    }
    // voting
    const board = (ideas && ideas.board) || [];
    const sig = 'V' + JSON.stringify(board);
    if (el.dataset.sig === sig) return;
    el.dataset.sig = sig;
    const rule = `⭐ Nominate the best idea (${q.votesPerPlayer} nomination${q.votesPerPlayer > 1 ? 's' : ''})` +
      (q.removeAfter ? ` · 👎 ${q.removeAfter} thumbs-down removes an idea` : ' · 👎 thumbs-down ideas that miss');
    el.innerHTML = `${pts}<p class="text-sm text-muted font-semibold">${rule}</p>` + board.map(i => `
      <div class="glass-2 p-3 flex items-center gap-2 ${i.removed ? 'opacity-40' : ''}">
        <p class="flex-1 font-semibold ${i.removed ? 'line-through' : ''}">${esc(i.text)}${i.mine ? ' <span class="chip chip-cyan !text-[10px] !py-0">yours</span>' : ''}${i.removed ? ' <span class="chip chip-red !text-[10px] !py-0">removed</span>' : ''}</p>
        ${i.mine || i.removed ? `<span class="text-sm text-muted">⭐ ${i.up}</span>` : `
          <button data-id="${i.id}" data-v="${i.myVote === 1 ? 0 : 1}" class="btn btn-sm ${i.myVote === 1 ? 'btn-gold' : ''}" title="Nominate">⭐ ${i.up}</button>
          <button data-id="${i.id}" data-v="${i.myVote === -1 ? 0 : -1}" class="btn btn-sm ${i.myVote === -1 ? 'btn-danger' : ''}" title="Thumbs down">👎</button>`}
      </div>`).join('');
    el.querySelectorAll('button[data-id]').forEach(b => b.onclick = () => api.vote(+b.dataset.id, +b.dataset.v));
  }

  /* ================================================================ STAGE */

  function bar(label, n, total, color) {
    const pct = total ? Math.round(n / total * 100) : 0;
    return `<div class="flex items-center gap-3">
      <span class="w-[30%] min-w-0 truncate font-semibold text-[clamp(.9rem,1.6vw,2rem)]">${label}</span>
      <div class="flex-1 h-[clamp(1.4rem,3.6vh,3.2rem)] rounded-xl overflow-hidden" style="background:rgba(255,255,255,.08)">
        <div class="h-full rounded-xl transition-all duration-700" style="width:${n ? Math.max(pct, 3) : 0}%;background:${color}"></div></div>
      <span class="w-[5.5em] text-right font-display font-bold text-[clamp(.9rem,1.6vw,2rem)]">${n} <span class="text-muted text-[.7em]">${pct}%</span></span></div>`;
  }

  /** Word cloud: biggest words in the middle, colors cycling. */
  function cloudHtml(words) {
    if (!words || !words.length) return `<div class="h-full grid place-items-center text-muted text-[clamp(1.2rem,2.4vw,3rem)] bob">☁️ Waiting for words…</div>`;
    const max = words[0].n, colors = ['#fde047', '#ff4fd8', '#22d3ee', '#a3e635', '#fb923c', '#c084fc', '#60a5fa', '#f472b6'];
    const arranged = [];
    words.forEach((w, i) => i % 2 ? arranged.push(w) : arranged.unshift(w));
    return `<div class="h-full flex flex-wrap items-center justify-center content-center gap-x-[clamp(.6rem,1.6vw,2rem)] gap-y-1 px-4">` + arranged.map((w, i) => {
      const size = 1 + 3.6 * Math.sqrt(w.n / max);
      const rot = (w.w.length + i) % 7 === 0 ? -6 : (w.w.length + i) % 5 === 0 ? 5 : 0;
      return `<span class="font-display font-bold leading-none animate-pop-in" style="font-size:clamp(${(size * .55).toFixed(2)}rem, ${(size * 1.15).toFixed(2)}vw, ${(size * 1.6).toFixed(2)}rem);color:${colors[(w.w.charCodeAt(0) + i) % colors.length]};transform:rotate(${rot}deg)" title="${w.n}">${esc(w.w)}</span>`;
    }).join('') + `</div>`;
  }

  /**
   * Big-screen area for non multiple-choice questions.
   * Returns true when it handled the question (then the classic option grid is hidden).
   */
  function stageArea(el, d) {
    const q = d.q, t = q.type, live = d.live || {}, revealed = d.phase !== 'QUESTION';
    if (t === 'mc' || t === 'tf') return false;
    let html = '';
    if (t === 'poll') {
      const total = (live.counts || []).reduce((a, b) => a + b, 0);
      html = `<div class="h-full flex flex-col justify-center gap-[clamp(.5rem,1.6vh,1.4rem)] max-w-[1400px] w-full mx-auto">` +
        q.options.map((o, i) => bar(`${RQ.shape(i).replace('class="shape"', 'class="shape !w-[1.6em] !h-[1.6em] !inline-grid mr-2 align-middle ans-' + i + '" style="background:var(--c)"')}${esc(o)}`,
          (live.counts || [])[i] || 0, total, `ans-${i}`, '')).join('').replaceAll('ans-0"', 'ans-0" style="background:var(--a0)"') + `</div>`;
      html = html.replace(/class="h-full (ans-\d) rounded-xl/g, 'style="background:var(--c)" class="h-full $1 rounded-xl');
    } else if (t === 'rating') {
      const total = (live.counts || []).reduce((a, b) => a + b, 0);
      html = `<div class="h-full grid gap-[clamp(1rem,3vw,4rem)] items-center" style="grid-template-columns:minmax(0,1fr) minmax(0,2fr)">
        <div class="text-center"><p class="font-display font-bold text-[clamp(4rem,10vw,12rem)] leading-none text-amber-200">${live.avg ?? '–'}</p>
          <p class="text-muted font-bold text-[clamp(.9rem,1.4vw,1.8rem)]">average of ${q.scale} · ${live.n || 0} vote${live.n === 1 ? '' : 's'}</p>
          <p class="text-[clamp(1.6rem,3vw,3.6rem)] mt-2">${'★'.repeat(Math.round(live.avg || 0)).padEnd(q.scale, '☆')}</p></div>
        <div class="flex flex-col gap-[clamp(.3rem,1vh,.8rem)]">${(live.counts || []).map((n, i) => bar(`${i + 1}${i === 0 ? ' · ' + esc(q.lowLabel) : i === q.scale - 1 ? ' · ' + esc(q.highLabel) : ''}`, n, total, '', '')).join('')}</div></div>`;
      html = html.replace(/class="h-full  rounded-xl/g, 'style="background:linear-gradient(90deg,#fde047,#f59e0b)" class="h-full rounded-xl');
    } else if (t === 'wordcloud') {
      html = cloudHtml(live.words);
    } else if (t === 'typeans') {
      html = !revealed ? `<div class="h-full grid place-items-center text-center"><div><div class="text-[clamp(4rem,10vw,10rem)] bob">⌨️</div>
          <p class="font-display font-bold text-[clamp(1.4rem,3vw,3.6rem)]">Type your answer on your phone!</p></div></div>`
        : `<div class="h-full flex flex-col items-center justify-center gap-[clamp(.6rem,2vh,2rem)] text-center">
          <p class="chip chip-green !text-[clamp(.9rem,1.4vw,1.6rem)]">Correct answer</p>
          <p class="font-display font-bold text-[clamp(2.4rem,6vw,7rem)] leading-none text-emerald-300">${esc((q.accepted || [])[0] || '')}</p>
          ${(q.accepted || []).length > 1 ? `<p class="text-muted text-[clamp(.8rem,1.2vw,1.4rem)]">Also accepted: ${q.accepted.slice(1).map(esc).join(', ')}</p>` : ''}
          <div class="flex flex-wrap justify-center gap-2 max-w-[1300px]">${(live.answers || []).map(a => `<span class="chip ${a.correct ? 'chip-green' : 'chip-red'} !text-[clamp(.8rem,1.3vw,1.5rem)] !py-[.4em]">${esc(a.a)} × ${a.n}</span>`).join('')}</div></div>`;
    } else if (t === 'estimate') {
      const span = q.max - q.min || 1, at = v => Math.max(0, Math.min(100, (v - q.min) / span * 100));
      const dots = revealed ? (live.values || []).map(v => `<span class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-[clamp(.6rem,1vw,1.2rem)] h-[clamp(.6rem,1vw,1.2rem)] rounded-full bg-pink-400/80 border border-white/60" style="left:${at(v)}%"></span>`).join('') : '';
      const ans = revealed ? `<span class="absolute -top-[clamp(3rem,7vh,6rem)] -translate-x-1/2 text-center" style="left:${at(q.answer)}%"><span class="block font-display font-bold text-[clamp(1.6rem,3.4vw,4rem)] text-emerald-300 whitespace-nowrap">${fmtNum(q.answer)} ${esc(q.unit || '')}</span>
          <span class="block text-[clamp(1.2rem,2vw,2.4rem)]">⬇</span></span>
          ${q.tolerance ? `<span class="absolute top-0 bottom-0 bg-emerald-400/25" style="left:${at(q.answer - q.tolerance)}%;width:${at(q.answer + q.tolerance) - at(q.answer - q.tolerance)}%"></span>` : ''}` : '';
      html = `<div class="h-full flex flex-col justify-center gap-[clamp(1rem,4vh,4rem)] max-w-[1500px] w-full mx-auto px-6">
        ${!revealed ? `<p class="text-center font-display font-bold text-[clamp(1.4rem,3vw,3.6rem)]">🎯 Make your best guess on your phone!</p>` : ''}
        <div class="relative mt-[clamp(3rem,8vh,7rem)] h-[clamp(1.2rem,2.4vh,2.2rem)] rounded-full" style="background:linear-gradient(90deg,#3b82f6,#8b5cf6,#ff4fd8)">${ans}${dots}</div>
        <div class="flex justify-between font-bold text-muted text-[clamp(.9rem,1.4vw,1.6rem)]"><span>${fmtNum(q.min)}</span><span>${fmtNum(q.max)} ${esc(q.unit || '')}</span></div>
        ${revealed && live.closest && live.closest.length ? `<div class="flex flex-wrap justify-center gap-3">${live.closest.map((c, i) => `<span class="chip ${i === 0 ? 'chip-gold' : ''} !text-[clamp(.9rem,1.4vw,1.7rem)] !py-[.4em]">${['🥇', '🥈', '🥉'][i]} ${esc(c.emoji)} ${esc(c.name)} · ${fmtNum(c.v)}</span>`).join('')}</div>` : ''}</div>`;
    } else if (t === 'ranking') {
      const list = revealed ? (q.correctOrder || []).map((x, i) => ({ text: x, n: i + 1 })) : q.items.map(x => ({ text: x.text, n: '?' }));
      html = `<div class="h-full flex flex-col justify-center gap-[clamp(.4rem,1.2vh,1.1rem)] max-w-[1100px] w-full mx-auto">${list.map((x, i) => `
        <div class="ans ans-${i % 6} rise-in !py-[clamp(.5rem,1.4vh,1.3rem)]" style="animation-delay:${i * 90}ms"><span class="shape font-display">${x.n}</span><span class="txt text-[clamp(1rem,2vw,2.6rem)]">${esc(x.text)}</span></div>`).join('')}</div>`;
    } else if (t === 'ideas') {
      const ideas = (live.ideas || []).slice();
      const stage = q.ideaStage;
      if (revealed) ideas.sort((a, b) => (a.removed - b.removed) || (b.up - a.up) || (a.down - b.down));
      const top = revealed && ideas.length && !ideas[0].removed && ideas[0].up > 0 ? ideas[0].id : null;
      const head = !revealed ? (stage === 'submit'
        ? `<p class="text-center font-display font-bold text-[clamp(1.2rem,2.4vw,3rem)]">💡 Share your ideas on your phone! <span class="chip chip-cyan !text-[.55em] align-middle">${ideas.length} shared</span></p>`
        : `<p class="text-center font-display font-bold text-[clamp(1.2rem,2.4vw,3rem)]">⭐ Vote for the best idea!${q.removeAfter ? ` <span class="chip chip-red !text-[.5em] align-middle">${q.removeAfter} 👎 removes an idea</span>` : ''}</p>`)
        : `<p class="text-center font-display font-bold text-[clamp(1.2rem,2.4vw,3rem)]">🏆 The best ideas</p>`;
      const cards = ideas.map((i, k) => `
        <div class="glass !rounded-2xl p-[clamp(.6rem,1.2vw,1.4rem)] flex flex-col gap-2 animate-pop-in ${i.removed ? 'opacity-30' : ''} ${i.id === top ? 'ring-glow' : ''}" style="animation-delay:${(k % 12) * 40}ms">
          <p class="font-semibold text-[clamp(.9rem,1.5vw,1.9rem)] leading-snug ${i.removed ? 'line-through' : ''}">${i.id === top ? '👑 ' : ''}${esc(i.text)}</p>
          ${stage === 'vote' || revealed ? `<div class="flex items-center gap-3 text-[clamp(.8rem,1.2vw,1.5rem)] font-bold"><span class="text-amber-200">⭐ ${i.up}</span><span class="text-rose-300">👎 ${i.down}</span>
            ${revealed && i.name ? `<span class="ml-auto text-muted">${esc(i.emoji)} ${esc(i.name)}</span>` : ''}${i.removed ? '<span class="ml-auto chip chip-red !text-[.8em]">removed</span>' : ''}</div>` : ''}
        </div>`).join('');
      html = `<div class="h-full flex flex-col gap-[clamp(.5rem,1.4vh,1.2rem)]">${head}
        <div class="flex-1 min-h-0 overflow-hidden grid gap-[clamp(.4rem,1vw,1rem)] content-start" style="grid-template-columns:repeat(auto-fill,minmax(min(100%,clamp(14rem,22vw,26rem)),1fr))">
        ${cards || '<div class="col-span-full text-center text-muted text-[clamp(1rem,2vw,2.4rem)] bob mt-8">Waiting for the first idea…</div>'}</div></div>`;
    }
    if (el.dataset.html !== html) { el.dataset.html = html; el.innerHTML = html; }
    return true;
  }

  /** One-line result summary under the question on the big screen. */
  function statsLine(d) {
    const q = d.q, live = d.live || {};
    if (q.type === 'mc' || q.type === 'tf') {
      const counts = live.counts || [], total = counts.reduce((a, b) => a + b, 0), corr = counts[q.correctIndex] || 0;
      return `✅ ${corr} of ${total} correct${total ? ` (${Math.round(corr / total * 100)}%)` : ''}`;
    }
    if (q.scored) return `✅ ${live.correct || 0} of ${live.n || 0} correct`;
    if (q.type === 'ideas') return `💡 ${(live.ideas || []).length} ideas`;
    const n = live.n ?? (live.counts || []).reduce((a, b) => a + b, 0);
    return `🙌 ${n} response${n === 1 ? '' : 's'}`;
  }

  return { INFO, prizeLabel, prizeRule, phoneInput, phoneLocked, phoneIdeas, correctText, stageArea, statsLine, cloudHtml };
})();
