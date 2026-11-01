<?php
/* Live control: the launched game. One big "next step" button; everything else lives in the ☰ menu. */
require_once __DIR__ . '/lib.php';
$me = current_admin();
if (!$me) { header('Location: login.php'); exit; }
if (empty(get_state()['launched'])) { header('Location: setup.php#launch'); exit; }
$pageTitle = 'Live — ' . APP_NAME;
?><!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/partials/head.php'; ?></head>
<body class="overflow-hidden">
<div class="blobs"><i></i><i></i><i></i></div>
<script>window.RQ_CSRF = <?= json_encode($_SESSION['csrf']) ?>; RQ.game = <?= json_encode($me['handle']) ?>;</script>

<div id="shell" class="live-shell">
  <!-- ======================= SIMPLE CONTROL BAR ======================= -->
  <div class="controls px-2 md:px-3 pt-2">
    <div class="glass !rounded-2xl px-3 py-2 flex items-center gap-2 md:gap-3 flex-wrap" style="background:rgba(20,15,55,.78)">
      <a href="setup.php" class="brand-logo-slot logo !text-base shrink-0" title="Back to setup"><span class="logo-badge !w-9 !h-9"><span class="!w-7 !h-7">⚡</span></span></a>
      <div class="min-w-0 leading-tight">
        <p id="stQ" class="font-display font-bold text-lg truncate"></p>
        <p id="stPhase" class="text-xs text-muted font-semibold truncate"></p>
      </div>
      <span id="stPlayers" class="chip chip-green !text-sm"></span>
      <button id="autoPill" onclick="LV.toggleAuto()" class="chip !text-sm !py-1.5 cursor-pointer" title="Click to turn automatic prizes on or off"></button>

      <div class="ml-auto flex items-center gap-2">
        <p id="hint" class="hidden xl:block text-xs text-muted max-w-[260px] text-right leading-snug"></p>
        <button id="btnPrimary" onclick="LV.primary()" class="btn btn-go btn-lg !py-2.5 !px-5 min-w-[13rem] !justify-start text-left">
          <i id="primIcon" class="fa-solid fa-play text-xl"></i>
          <span class="leading-tight"><span id="primLabel" class="block font-display text-lg"></span><span id="primSub" class="block text-[11px] opacity-85 font-semibold"></span></span>
        </button>
        <button id="btnWinner" onclick="LV.draw()" class="btn btn-gold !py-3" title="Draw a prize winner now (W)"><i class="fa-solid fa-gift"></i><span class="hidden lg:inline"> Winner!</span></button>
        <button onclick="LV.present()" class="btn btn-primary !py-3" title="Presentation mode — full screen (F)"><i class="fa-solid fa-expand"></i><span class="hidden lg:inline"> Present</span></button>
        <button onclick="LV.menu(true)" class="btn !py-3 !px-4" title="More controls"><i class="fa-solid fa-bars text-lg"></i></button>
      </div>
    </div>
  </div>

  <!-- ======================= STAGE ======================= -->
  <div class="relative min-h-0">
    <?php include __DIR__ . '/partials/stage_markup.php'; ?>
  </div>
</div>

<!-- ======================= PRESENTER DOCK (only in presentation mode) ======================= -->
<div id="dock" class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-40 glass !rounded-2xl p-2 flex items-center gap-2 transition-opacity duration-500" style="background:rgba(20,15,55,.88)">
  <button onclick="LV.primary()" id="dockPrimary" class="btn btn-go btn-lg !py-2.5"></button>
  <button onclick="LV.draw()" id="dockWinner" class="btn btn-gold !py-3" title="Draw a winner (W)"><i class="fa-solid fa-gift"></i></button>
  <button onclick="LV.menu(true)" class="btn !py-3" title="Menu"><i class="fa-solid fa-bars"></i></button>
  <button onclick="LV.present()" class="btn !py-3" title="Exit presentation (Esc)"><i class="fa-solid fa-compress"></i></button>
</div>

<!-- ======================= MENU DRAWER ======================= -->
<div id="drawerBg" class="hidden fixed inset-0 z-50" style="background:rgba(5,4,20,.55)" onclick="LV.menu(false)"></div>
<aside id="drawer" class="fixed top-0 right-0 bottom-0 z-50 w-[min(420px,92vw)] p-4 overflow-y-auto custom-scrollbar space-y-4 translate-x-full transition-transform duration-300"
       style="background:rgba(16,12,44,.97);border-left:1px solid var(--line)">
  <div class="flex items-center justify-between">
    <h2 class="font-display text-2xl font-bold">Controls</h2>
    <button class="btn btn-ghost" onclick="LV.menu(false)"><i class="fa-solid fa-xmark text-xl"></i></button>
  </div>

  <section class="glass-2 p-3 space-y-2">
    <p class="lbl !mb-0">❓ Questions</p>
    <div class="flex gap-2"><select id="jumpSel" class="field !py-2 flex-1"></select><button class="btn" onclick="LV.jump()">Go</button></div>
    <div>
      <input id="pullInput" class="field !py-2" placeholder="🔎 Pull up a question by name…" autocomplete="off" onfocus="LV.pullSearch()" oninput="LV.pullSearch()">
      <div id="pullResults" class="hidden mt-1 max-h-72 overflow-y-auto custom-scrollbar space-y-1"></div>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <button id="btnEnd" class="btn" onclick="LV.endQ()"><i class="fa-solid fa-forward-step"></i> End timer</button>
      <button id="btnSkip" class="btn" onclick="LV.next(true)"><i class="fa-solid fa-forward"></i> Next question</button>
    </div>
  </section>

  <section class="glass-2 p-3 space-y-2">
    <p class="lbl !mb-0">🎁 Prizes</p>
    <label class="flex items-center gap-3 cursor-pointer"><span class="toggle"><input type="checkbox" id="quickAuto" onchange="LV.setAuto(this.checked)"><span></span></span><span class="font-semibold">Automatic prize after each question</span></label>
    <div class="flex gap-2">
      <select id="drawMode" class="field !py-2 flex-1">
        <option value="random_correct">🎲 Random correct answer</option>
        <option value="random_player">🎟️ Random player</option>
        <option value="fastest">⚡ Fastest correct / best idea</option>
      </select>
      <button class="btn btn-gold" onclick="LV.draw()"><i class="fa-solid fa-gift"></i> Draw</button>
    </div>
    <div id="roundWinners" class="flex flex-wrap gap-1.5"></div>
  </section>

  <section class="glass-2 p-3 space-y-2">
    <p class="lbl !mb-0">📺 Show on screen</p>
    <div class="grid grid-cols-3 gap-2">
      <button class="btn !flex-col !py-3" onclick="LV.phase('LOBBY')"><i class="fa-solid fa-qrcode text-xl"></i> Join QR</button>
      <button class="btn !flex-col !py-3" onclick="LV.phase('LEADERBOARD')"><i class="fa-solid fa-ranking-star text-xl"></i> Leaders</button>
      <button class="btn btn-purple !flex-col !py-3" onclick="LV.phase('PODIUM')"><i class="fa-solid fa-trophy text-xl"></i> Podium</button>
    </div>
  </section>

  <section class="glass-2 p-3 space-y-2">
    <p class="lbl !mb-0">📚 Question bank</p>
    <div class="flex gap-2"><select id="liveBankSel" class="field !py-2 flex-1"></select><button class="btn" onclick="LV.useBank(+document.getElementById('liveBankSel').value)">Load</button></div>
  </section>

  <section class="glass-2 p-3 space-y-2">
    <p class="lbl !mb-0">🔊 Sound</p>
    <div id="volBox"></div>
    <p id="soundNames" class="help !mt-0"></p>
  </section>

  <section class="grid grid-cols-2 gap-2">
    <a class="btn" href="stage.php?g=<?= htmlspecialchars($me['handle']) ?>" target="_blank"><i class="fa-solid fa-display"></i> Projector window</a>
    <a class="btn" href="setup.php"><i class="fa-solid fa-sliders"></i> Back to setup</a>
  </section>

  <section class="p-3 rounded-2xl space-y-2" style="background:rgba(244,63,94,.10);border:1px solid rgba(244,63,94,.45)">
    <p class="lbl !mb-0 !text-rose-200">⚠️ Danger zone</p>
    <button class="btn btn-danger w-full !justify-center" onclick="LV.resetGame()"><i class="fa-solid fa-triangle-exclamation"></i> Reset game — remove all players</button>
    <p class="help !mt-0">Deletes every player and their scores. You'll get a full warning first.</p>
  </section>

  <section class="glass-2 p-3 text-sm text-muted space-y-1">
    <p class="lbl !mb-1">⌨️ Keyboard &amp; presentation clicker</p>
    <p><b class="text-white">→ / Space / Page Down</b> — do the next step (big button)</p>
    <p><b class="text-white">W</b> winner · <b class="text-white">L</b> leaderboard · <b class="text-white">P</b> podium · <b class="text-white">F</b> present · <b class="text-white">Esc</b> exit</p>
  </section>
</aside>

<script src="assets/vendor/xlsx.mini.min.js" defer></script>
<script src="assets/qtypes.js?v=6"></script>
<script src="assets/stage.js?v=10"></script>
<script src="assets/live.js?v=8"></script>
</body>
</html>
