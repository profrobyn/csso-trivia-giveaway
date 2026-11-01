<?php /* Audience "stage". Used by live.php (host) and stage.php (projector). It fills whatever box it is placed in. */ ?>
<div id="stageRoot" class="stage">
  <div id="stageWaveOverlay" class="pointer-events-none absolute top-4 right-4 z-30 flex flex-col gap-2 items-end"></div>

  <!-- ============ NOT LAUNCHED ============ -->
  <div id="sClosed" class="stage-screen hidden items-center justify-center text-center">
    <div class="text-[clamp(4rem,10vw,9rem)] bob">🛠️</div>
    <h2 class="s-title">Getting things ready…</h2>
    <p class="s-sub">The host is setting up the game. This screen will update automatically.</p>
  </div>

  <!-- ============ LOBBY ============ -->
  <div id="sLobby" class="stage-screen hidden">
    <div class="flex items-center justify-between gap-4 flex-wrap">
      <div class="flex items-center gap-3">
        <span class="logo-badge" style="width:clamp(2.4rem,3.4vw,4rem);height:clamp(2.4rem,3.4vw,4rem)"><span style="width:82%;height:82%;font-size:clamp(1.2rem,1.8vw,2.2rem)">⚡</span></span>
        <div>
          <p id="sLobbyTitle" class="font-display font-bold text-[clamp(1.2rem,2vw,2.4rem)] leading-none"></p>
          <p id="sLobbyEvent" class="text-muted font-semibold text-[clamp(.8rem,1.1vw,1.3rem)]"></p>
        </div>
      </div>
      <div id="sRules" class="flex flex-wrap gap-2"></div>
    </div>

    <div id="sWifiBanner" class="hidden glass !rounded-2xl px-5 py-3 flex items-center gap-4 border-rose-400/60" style="background:rgba(244,63,94,.15)">
      <span class="text-[clamp(1.5rem,2.5vw,3rem)]">📶</span>
      <p id="sWifiText" class="font-bold text-rose-100 text-[clamp(.9rem,1.4vw,1.6rem)]"></p>
    </div>

    <div class="flex-1 min-h-0 grid gap-[clamp(1rem,3vw,4rem)] items-center" style="grid-template-columns: minmax(0,1.1fr) minmax(0,1fr)">
      <div class="flex flex-col gap-[clamp(.8rem,2.4vh,2.4rem)] min-w-0">
        <h2 class="s-title">Join the <span class="grad-text">game!</span></h2>
        <ol class="flex flex-col gap-[clamp(.4rem,1.4vh,1.2rem)] text-[clamp(1rem,1.8vw,2.2rem)] font-semibold">
          <li class="flex items-center gap-3"><span class="chip chip-pink !text-[1em] !px-[.7em]">1</span> Scan the QR code with your phone camera</li>
          <li class="flex items-center gap-3"><span class="chip chip-cyan !text-[1em] !px-[.7em]">2</span> Pick a name and an emoji</li>
          <li class="flex items-center gap-3"><span class="chip chip-gold !text-[1em] !px-[.7em]">3</span> Answer fast — speed = points!</li>
        </ol>
        <div class="glass !rounded-2xl p-[clamp(.8rem,1.6vw,1.8rem)] flex flex-col gap-[clamp(.3rem,1vh,.9rem)]">
          <div class="flex items-baseline gap-4 flex-wrap">
            <p class="text-muted font-bold uppercase tracking-widest text-[clamp(.65rem,.9vw,1rem)]">Game code</p>
            <p id="sCode" class="big-code shimmer"></p>
          </div>
          <p class="text-muted font-semibold text-[clamp(.75rem,1vw,1.15rem)]">No camera? Go to <span id="sUrl" class="font-bold text-cyan-200 break-all"></span></p>
        </div>
        <div class="flex items-center gap-4">
          <div class="glass !rounded-2xl px-5 py-2 text-center">
            <p id="sLobbyCount" class="font-display font-bold text-[clamp(2rem,4vw,5rem)] leading-none text-emerald-300">0</p>
            <p class="text-muted font-bold uppercase tracking-widest text-[clamp(.6rem,.8vw,.95rem)]">players in</p>
          </div>
          <div id="sFaces" class="faces !justify-start flex-1 min-w-0 max-h-[18vh] overflow-hidden"></div>
        </div>
      </div>
      <div class="flex items-center justify-center gap-[clamp(.8rem,2vw,2.5rem)] min-h-0 h-full">
        <div id="sWifiBox" class="hidden flex-col items-center gap-2 text-center w-[min(32%,30vh)] shrink-0">
          <p class="chip chip-cyan !text-[clamp(.7rem,1vw,1.2rem)]">Step 1 · Join Wi-Fi</p>
          <div class="qr-card w-full !p-[clamp(.4rem,.8vw,.9rem)]"><div id="sWifiQr"></div></div>
          <p class="font-bold text-[clamp(.8rem,1.2vw,1.5rem)] leading-tight break-all">📶 <span id="sWifiName"></span></p>
          <p class="text-muted font-semibold text-[clamp(.7rem,1vw,1.2rem)] break-all" id="sWifiPassLine"></p>
        </div>
        <div class="flex flex-col items-center gap-2 w-full max-w-[min(100%,62vh)]">
          <p id="sStep2" class="hidden chip chip-pink !text-[clamp(.7rem,1vw,1.2rem)]">Step 2 · Join the game</p>
          <div class="qr-card w-full bob" style="animation-duration:5s"><div id="sQr"></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ QUESTION / REVEAL ============ -->
  <div id="sQuestion" class="stage-screen hidden">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-2 flex-wrap">
        <span id="sQNum" class="chip chip-gold !text-[clamp(.8rem,1.2vw,1.4rem)] !py-[.4em] !px-[1em]"></span>
        <span id="sTypeChip" class="chip chip-cyan !text-[clamp(.75rem,1.05vw,1.25rem)] !py-[.4em] !px-[1em] hidden"></span>
        <span id="sPrizeChip" class="chip chip-green !text-[clamp(.75rem,1.05vw,1.25rem)] !py-[.4em] !px-[1em] hidden"></span>
      </div>
      <div class="flex items-center gap-[clamp(.5rem,1.2vw,1.5rem)]">
        <div class="flex items-center gap-2 glass !rounded-2xl px-3 py-1.5">
          <div id="sQrSmall" class="bg-white rounded-lg p-1 w-[clamp(44px,4.5vw,84px)]"></div>
          <div class="leading-tight"><p class="text-muted text-[clamp(.55rem,.75vw,.85rem)] font-bold uppercase tracking-wider">Join anytime</p>
            <p id="sCodeSmall" class="font-display font-bold tracking-[.18em] text-[clamp(.9rem,1.4vw,1.7rem)]"></p></div>
        </div>
        <div class="glass !rounded-2xl px-4 py-1.5 text-center">
          <p id="sAnswered" class="font-display font-bold text-[clamp(1.2rem,2.2vw,2.8rem)] leading-none text-emerald-300">0/0</p>
          <p class="text-muted text-[clamp(.55rem,.75vw,.85rem)] font-bold uppercase tracking-wider">answered</p>
        </div>
        <div id="sTimerBox"></div>
      </div>
    </div>

    <div class="relative flex-1 min-h-0 flex flex-col gap-[clamp(.6rem,1.8vh,1.8rem)]">
      <div id="sGetReady" class="hidden absolute inset-0 z-10 rounded-3xl flex flex-col items-center justify-center" style="background:rgba(11,8,32,.92)">
        <p class="font-display font-bold uppercase tracking-[.3em] text-[clamp(1rem,2vw,2.4rem)] text-pink-300">Get ready!</p>
        <p id="sGetReadyNum" class="cartoon-num text-[clamp(6rem,18vw,18rem)] leading-none count-pop">3</p>
        <p id="sGetReadyPrize" class="text-[clamp(.9rem,1.5vw,1.8rem)] font-bold text-emerald-200"></p>
      </div>
      <div class="glass !rounded-[clamp(1rem,2vw,2rem)] px-[clamp(1rem,2.4vw,3rem)] py-[clamp(.8rem,2.2vh,2.4rem)]">
        <h2 id="sQText" class="s-question"></h2>
      </div>
      <div id="sOptions" class="s-options"></div>
      <div id="sTypeArea" class="hidden flex-1 min-h-0"></div>
      <div id="sNote" class="hidden glass !rounded-2xl px-[clamp(.9rem,1.8vw,2rem)] py-[clamp(.5rem,1.3vh,1.2rem)] flex items-start gap-3 rise-in" style="background:rgba(34,211,238,.12);border-color:rgba(34,211,238,.45)">
        <span class="text-[clamp(1.3rem,2.2vw,2.6rem)] leading-none">💬</span>
        <p id="sNoteText" class="font-semibold text-cyan-50 text-[clamp(1rem,1.8vw,2.2rem)] leading-snug"></p>
      </div>
      <div id="sStats" class="hidden flex items-center gap-4 flex-wrap">
        <span id="sCorrectStats" class="chip chip-green !text-[clamp(.8rem,1.2vw,1.4rem)] !py-[.4em] !px-[1em]"></span>
        <span id="sNextPrize" class="chip chip-gold !text-[clamp(.8rem,1.2vw,1.4rem)] !py-[.4em] !px-[1em] hidden"></span>
      </div>
    </div>
  </div>

  <!-- ============ PRIZE DRAW (reel) ============ -->
  <div id="sDraw" class="stage-screen hidden items-center justify-center text-center">
    <span id="sDrawBadge" class="chip chip-gold !text-[clamp(.9rem,1.4vw,1.6rem)] !py-[.4em] !px-[1.2em]"></span>
    <h2 id="sDrawTitle" class="s-title !text-[clamp(1.8rem,4vw,5rem)]"></h2>
    <div class="glass ring-glow !rounded-[2rem] px-[clamp(2rem,5vw,6rem)] py-[clamp(1.2rem,4vh,4rem)] min-w-[min(80vw,40rem)]">
      <div id="sReelEmoji" class="text-[clamp(4rem,11vw,12rem)] leading-none slot-spin">🎲</div>
      <p id="sReelName" class="font-display font-bold text-[clamp(1.6rem,3.6vw,4.4rem)] text-amber-200 mt-3 min-h-[1.2em]"></p>
    </div>
  </div>

  <!-- ============ WINNER + LEADERBOARD ============ -->
  <div id="sWinner" class="stage-screen hidden">
    <div class="flex-1 min-h-0 grid gap-[clamp(1rem,2.4vw,3rem)] items-stretch" style="grid-template-columns: minmax(0,1fr) minmax(0,1.15fr)">
      <div class="glass ring-glow !rounded-[2rem] flex flex-col items-center justify-center text-center p-[clamp(1rem,3vw,3rem)] gap-[clamp(.4rem,1.4vh,1.4rem)]">
        <span id="sWinBadge" class="chip chip-green !text-[clamp(.8rem,1.2vw,1.4rem)] !py-[.4em] !px-[1em]"></span>
        <div id="sWinEmoji" class="text-[clamp(5rem,13vw,14rem)] leading-none animate-pop-in"></div>
        <h3 id="sWinName" class="font-display font-bold text-[clamp(2rem,4.6vw,5.6rem)] leading-none break-words max-w-full"></h3>
        <p id="sWinSub" class="font-bold text-amber-200 text-[clamp(.95rem,1.6vw,2rem)]"></p>
        <div id="sWinOthers" class="text-muted text-[clamp(.75rem,1.1vw,1.3rem)] space-y-1"></div>
      </div>
      <div class="sBoardBox min-h-0 flex flex-col"></div>
    </div>
  </div>

  <!-- ============ LEADERBOARD ============ -->
  <div id="sBoard" class="stage-screen hidden"><div class="sBoardBox flex-1 min-h-0 flex flex-col w-full max-w-[1500px] mx-auto"></div></div>

  <!-- ============ PODIUM ============ -->
  <div id="sPodium" class="stage-screen hidden text-center relative">
    <div>
      <span id="sPodiumScope" class="chip chip-gold !text-[clamp(.8rem,1.2vw,1.4rem)] !py-[.4em] !px-[1em]"></span>
      <h2 class="s-title mt-2">🏆 <span class="shimmer">Champions</span> 🏆</h2>
    </div>
    <div class="flex-1 min-h-0 flex items-end justify-center gap-[clamp(.6rem,2vw,2.5rem)] w-full max-w-[1300px] mx-auto">
      <div class="podium-col"><div id="sP2e" class="text-[clamp(3rem,7vw,8rem)] leading-none animate-pop-in" style="animation-delay:1.3s"></div>
        <p id="sP2n" class="font-display font-bold text-[clamp(1rem,2.2vw,2.6rem)] break-words max-w-full"></p><p id="sP2s" class="text-muted font-bold text-[clamp(.8rem,1.3vw,1.5rem)] mb-2"></p>
        <div class="podium-block p2">2</div></div>
      <div class="podium-col"><div class="text-[clamp(2rem,4vw,4.5rem)] bob">👑</div><div id="sP1e" class="text-[clamp(4rem,9vw,10rem)] leading-none animate-pop-in" style="animation-delay:1.8s"></div>
        <p id="sP1n" class="font-display font-bold text-[clamp(1.3rem,3vw,3.6rem)] text-amber-200 break-words max-w-full"></p><p id="sP1s" class="text-emerald-300 font-bold text-[clamp(.9rem,1.5vw,1.8rem)] mb-2"></p>
        <div class="podium-block p1">1</div></div>
      <div class="podium-col"><div id="sP3e" class="text-[clamp(3rem,6vw,7rem)] leading-none animate-pop-in" style="animation-delay:.9s"></div>
        <p id="sP3n" class="font-display font-bold text-[clamp(1rem,2vw,2.4rem)] break-words max-w-full"></p><p id="sP3s" class="text-muted font-bold text-[clamp(.8rem,1.3vw,1.5rem)] mb-2"></p>
        <div class="podium-block p3">3</div></div>
    </div>
    <div id="sPodiumRest" class="flex flex-wrap justify-center gap-2"></div>
    <div id="sSignup" class="hidden self-center glass-2 !rounded-2xl p-1.5 pr-4 flex items-center gap-3 text-left max-w-full">
      <div id="sSignupQr" class="bg-white rounded-lg p-1 shrink-0 w-[clamp(3rem,4.5vw,5.5rem)] h-[clamp(3rem,4.5vw,5.5rem)] [&_img]:w-full [&_img]:h-full [&_canvas]:w-full [&_canvas]:h-full"></div>
      <div class="min-w-0"><p class="font-display font-bold text-[clamp(.85rem,1.3vw,1.6rem)] leading-tight">🎤 Host your own quiz — free!</p>
        <p class="text-muted font-semibold text-[clamp(.65rem,.9vw,1.05rem)]">Scan or visit <span id="sSignupUrl" class="text-cyan-200 break-all"></span></p></div>
    </div>
  </div>
</div>
