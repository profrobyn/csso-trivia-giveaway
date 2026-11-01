<?php
/* Player page (phones): index.php?g=GAMECODE — the QR code on the host's screen points here. */
require_once __DIR__ . '/lib.php';
$g = strtolower(preg_replace('/[^a-z0-9-]/i', '', $_GET['g'] ?? ''));
try { $hostAcct = $g !== '' ? admin_by_slug($g) : null; if ($hostAcct) $g = $hostAcct['handle']; } catch (Throwable $e) { }
$pageTitle = APP_NAME;
?><!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/partials/head.php'; ?></head>
<body>
  <div class="blobs"><i></i><i></i><i></i></div>
  <div class="min-h-[100dvh] flex flex-col max-w-lg mx-auto w-full">
    <header class="px-4 pt-[max(.8rem,env(safe-area-inset-top))] pb-2 flex items-center justify-between">
      <span class="logo"><span class="logo-badge"><span>⚡</span></span><span id="appTitle"><?= htmlspecialchars(APP_NAME) ?></span></span>
      <span id="connDot" class="chip !text-[11px]"><span class="w-2 h-2 rounded-full bg-slate-400"></span> connecting</span>
    </header>

    <main class="flex-1 flex flex-col px-3 pb-[max(.8rem,env(safe-area-inset-bottom))] gap-3">

      <!-- ============ GAME CODE ============ -->
      <section id="codeCard" class="hidden flex-1 flex flex-col justify-center gap-5 text-center rise-in">
        <div class="text-7xl bob">🎮</div>
        <div>
          <h1 class="font-display text-4xl font-bold">Ready to <span class="grad-text">play?</span></h1>
          <p class="text-muted mt-2">Scan the QR code on the big screen, or type the game code.</p>
        </div>
        <div class="glass p-5 space-y-3">
          <input id="codeEntry" maxlength="30" autocomplete="off" autocapitalize="none" placeholder="code or host name" class="field code-input !text-3xl !py-4">
          <button onclick="P.enterCode()" class="btn btn-primary btn-lg w-full">Let's go <i class="fa-solid fa-arrow-right"></i></button>
          <p id="codeErr" class="text-rose-300 text-sm font-semibold hidden"></p>
        </div>
      </section>

      <!-- ============ GAME NOT OPEN YET ============ -->
      <section id="closedCard" class="hidden flex-1 flex flex-col justify-center gap-4 text-center rise-in">
        <div class="text-7xl bob">⏳</div>
        <h1 class="font-display text-3xl font-bold">Almost time!</h1>
        <p class="text-muted">The host is still setting up the game.<br>This page will open by itself — keep it handy.</p>
      </section>

      <!-- ============ JOIN ============ -->
      <section id="joinCard" class="hidden flex flex-col gap-3 rise-in">
        <div class="text-center pt-1">
          <p id="joinEvent" class="chip chip-pink mx-auto"></p>
          <h1 class="font-display text-[2.1rem] leading-tight font-bold mt-2">Join the <span class="grad-text">fun!</span></h1>
          <p class="text-muted text-sm">Pick a name and an emoji so everyone can spot you on the big screen.</p>
        </div>

        <div id="joinWifi" class="hidden glass-2 p-3 text-left flex items-start gap-2.5" style="background:rgba(244,63,94,.14);border-color:rgba(244,63,94,.5)">
          <span class="text-xl">📶</span><p id="joinWifiText" class="text-sm font-semibold text-rose-100"></p>
        </div>

        <div class="glass p-4 space-y-4">
          <div>
            <label class="lbl" for="nameInput">Your name</label>
            <div class="flex items-center gap-2">
              <span id="nameEmoji" class="text-4xl w-12 text-center animate-pop-in">😀</span>
              <input type="text" id="nameInput" maxlength="30" autocomplete="nickname" placeholder="Type your name…" class="field !text-lg !font-bold">
            </div>
          </div>
          <div>
            <label class="lbl">Pick your emoji</label>
            <div id="emojiGrid" class="grid grid-cols-7 gap-1.5 max-h-[32vh] overflow-y-auto custom-scrollbar pr-1"></div>
          </div>

          <div id="takenBox" class="hidden rounded-2xl p-3 space-y-2" style="background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.5)">
            <p class="text-sm font-bold text-amber-200"><i class="fa-solid fa-triangle-exclamation"></i> <span id="takenMsg"></span></p>
            <p class="text-xs text-amber-100/80">Please choose a different name. <b>Played on an earlier day?</b> Enter your rejoin code:</p>
            <div class="flex gap-2">
              <input id="codeInput" maxlength="5" placeholder="CODE" class="field code-input !py-2">
              <button onclick="P.rejoin()" class="btn btn-gold">Rejoin</button>
            </div>
          </div>

          <button id="joinBtn" onclick="P.join()" class="btn btn-primary btn-xl w-full">Join game <i class="fa-solid fa-rocket"></i></button>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center text-xs font-semibold">
          <div class="glass-2 p-2.5"><div class="text-2xl">⚡</div>Faster answers score more</div>
          <div class="glass-2 p-2.5"><div class="text-2xl">🎁</div>Prizes every round</div>
          <div class="glass-2 p-2.5"><div class="text-2xl">📅</div>Scores add up each day</div>
        </div>
      </section>

      <!-- ============ REJOIN CODE NOTICE ============ -->
      <section id="codeNotice" class="hidden flex-1 flex flex-col justify-center gap-4 text-center rise-in">
        <div id="welcomeEmoji" class="text-8xl animate-pop-in"></div>
        <h1 class="font-display text-3xl font-bold">You're in! 🎉</h1>
        <div class="glass p-5 space-y-2">
          <p class="text-sm text-muted">This phone will remember you. Playing on a different phone or day? You'll need your <b class="text-white">rejoin code</b>:</p>
          <p id="codeBig" class="big-code !text-5xl shimmer py-2"></p>
          <p class="text-xs text-muted">📸 Take a screenshot! You can see it again by tapping your emoji.</p>
        </div>
        <button onclick="P.ackCode()" class="btn btn-primary btn-xl w-full">Let's play! <i class="fa-solid fa-arrow-right"></i></button>
      </section>

      <!-- ============ GAME ============ -->
      <section id="gameCard" class="hidden flex-1 flex flex-col gap-3">
        <div class="glass !rounded-2xl px-3 py-2.5 flex items-center justify-between gap-2">
          <button onclick="P.openMe()" class="flex items-center gap-2.5 text-left min-w-0" title="Change emoji / see rejoin code">
            <span id="meEmoji" class="text-4xl leading-none"></span>
            <span class="min-w-0">
              <span id="meName" class="block font-display font-bold text-lg leading-tight truncate"></span>
              <span id="meCode" class="block text-[11px] text-muted font-semibold"></span>
            </span>
          </button>
          <div class="text-right shrink-0">
            <p id="meScore" class="font-display font-bold text-xl text-emerald-300 leading-tight">0</p>
            <p id="meRank" class="text-[11px] text-amber-200 font-bold"></p>
          </div>
        </div>

        <!-- waiting -->
        <div id="wWait" class="flex-1 flex flex-col items-center justify-center text-center gap-4 glass p-6">
          <div id="waitEmoji" class="text-8xl bob"></div>
          <div>
            <h2 class="font-display text-3xl font-bold">You're locked in! 🚀</h2>
            <p class="text-muted mt-1">Eyes on the big screen — the next question is coming.</p>
          </div>
          <p id="waitDay" class="chip"></p>
          <button onclick="P.wave()" class="btn btn-gold btn-lg"><i class="fa-solid fa-hand"></i> Say hi on the big screen</button>
        </div>

        <!-- get ready -->
        <div id="wReady" class="hidden flex-1 flex flex-col items-center justify-center text-center glass p-6">
          <p class="font-display font-bold uppercase tracking-[.3em] text-pink-300">Get ready!</p>
          <p id="readyNum" class="cartoon-num text-[9rem] leading-none count-pop">3</p>
          <p id="readyPrize" class="text-sm text-emerald-200 font-bold mt-2"></p>
        </div>

        <!-- question -->
        <div id="wQuestion" class="hidden flex-1 flex flex-col gap-3 min-h-0">
          <div class="flex items-center justify-between gap-2">
            <span id="qNum" class="chip chip-gold"></span>
            <div id="qTimerBox"></div>
          </div>
          <p id="qPrize" class="hidden chip chip-green !whitespace-normal !text-left"></p>
          <h3 id="qText" class="font-display text-xl font-semibold leading-snug"></h3>
          <div id="qButtons" class="flex-1 grid grid-cols-2 gap-2.5 min-h-[46vh]" style="grid-auto-rows:1fr"></div>
        </div>

        <!-- idea board -->
        <div id="wIdeas" class="hidden flex-1 flex flex-col gap-3 min-h-0">
          <div class="flex items-center justify-between gap-2">
            <span id="ideaNum" class="chip chip-gold"></span>
            <div id="ideaTimerBox"></div>
          </div>
          <h3 id="ideaText" class="font-display text-xl font-semibold leading-snug"></h3>
          <div id="ideaArea" class="flex flex-col gap-2.5"></div>
        </div>

        <!-- locked -->
        <div id="wLocked" class="hidden flex-1 flex flex-col items-center justify-center text-center gap-3 glass p-6">
          <div id="lockedTile" class="w-28 h-28 rounded-3xl grid place-items-center text-5xl animate-pop-in"></div>
          <h3 class="font-display text-3xl font-bold">Answer locked in!</h3>
          <p id="lockedChoice" class="text-muted"></p>
        </div>

        <!-- feedback -->
        <div id="wFeedback" class="hidden flex-1 flex flex-col items-center justify-center text-center gap-3 glass p-6">
          <div id="fbIconBox" class="w-24 h-24 rounded-full grid place-items-center text-5xl animate-pop-in"><i id="fbIcon" class="fa-solid"></i></div>
          <h3 id="fbTitle" class="font-display text-3xl font-bold"></h3>
          <p id="fbPoints" class="font-display text-2xl font-bold text-emerald-300"></p>
          <p id="fbKudos" class="text-amber-200 font-semibold italic"></p>
          <p id="fbNote" class="hidden w-full text-left rounded-2xl p-3 text-sm font-semibold text-cyan-50" style="background:rgba(34,211,238,.12);border:1px solid rgba(34,211,238,.45)"></p>
          <div class="grid grid-cols-2 gap-2 w-full">
            <div class="glass-2 p-3"><p class="text-[11px] text-muted uppercase tracking-wider font-bold" id="fbScoreLbl">Total</p><p id="fbTotal" class="font-display text-2xl font-bold text-emerald-300"></p></div>
            <div class="glass-2 p-3"><p class="text-[11px] text-muted uppercase tracking-wider font-bold">Standing</p><p id="fbRank" class="font-display text-2xl font-bold text-amber-200"></p></div>
          </div>
          <p id="fbPrize" class="chip chip-green !whitespace-normal"></p>
        </div>

        <!-- drawing -->
        <div id="wDrawing" class="hidden flex-1 flex flex-col items-center justify-center text-center gap-3 glass p-6">
          <div class="text-8xl slot-spin">🎰</div>
          <h3 class="font-display text-3xl font-bold text-amber-200">Drawing a winner…</h3>
          <p id="drawingMode" class="text-muted"></p>
        </div>

        <!-- winner -->
        <div id="wWin" class="hidden flex-1 flex flex-col items-center justify-center text-center gap-3 p-6 rounded-[1.4rem]">
          <div id="winEmoji" class="text-8xl animate-pop-in"></div>
          <h3 id="winTitle" class="font-display text-3xl font-bold"></h3>
          <p id="winSub" class="font-semibold"></p>
        </div>

        <!-- standings / podium -->
        <div id="wStand" class="hidden flex-1 flex flex-col items-center justify-center text-center gap-3 glass p-6">
          <div id="standEmoji" class="text-8xl animate-pop-in"></div>
          <h3 id="standTitle" class="font-display text-3xl font-bold shimmer"></h3>
          <p id="standSub" class="font-display text-xl font-bold"></p>
          <p id="standWins" class="chip chip-green"></p>
          <a id="signupCta" href="#" target="_blank" rel="noopener" class="hidden mt-3 w-full max-w-sm glass-2 !rounded-2xl p-4 text-left flex items-center gap-3 hover:bg-white/10 transition">
            <span class="text-4xl shrink-0">🎤</span>
            <span class="min-w-0"><span class="block font-display text-lg font-bold leading-tight">Host your own quiz!</span>
              <span class="block text-sm text-muted">Create a free account in a minute →</span></span>
          </a>
        </div>
      </section>
    </main>
  </div>

  <!-- player card / emoji modal -->
  <div id="meModal" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3" style="background:rgba(5,4,20,.75);backdrop-filter:blur(6px)">
    <div class="glass w-full max-w-md p-5 space-y-4 rise-in" style="background:rgba(20,15,55,.95)">
      <div class="flex items-center justify-between">
        <h3 class="font-display text-xl font-bold">Your player card</h3>
        <button onclick="P.closeMe()" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>
      <div class="glass-2 p-4 text-center">
        <p class="text-[11px] text-muted uppercase font-bold tracking-wider">Rejoin code (for another phone or day)</p>
        <p id="modalCode" class="big-code !text-4xl shimmer mt-1"></p>
      </div>
      <div>
        <p class="lbl">Change your emoji</p>
        <div id="modalEmojiGrid" class="grid grid-cols-7 gap-1.5 max-h-[40vh] overflow-y-auto custom-scrollbar"></div>
      </div>
      <button onclick="P.wave()" class="btn btn-gold w-full"><i class="fa-solid fa-hand"></i> Say hi on the big screen</button>
    </div>
  </div>

  <script>RQ.game = <?= json_encode($g) ?>;</script>
  <script src="assets/qtypes.js?v=6"></script>
  <script src="assets/player.js?v=7"></script>
</body>
</html>
