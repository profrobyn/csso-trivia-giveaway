<?php
/* Setup: everything the host prepares before launching — event, questions, prizes, settings — then Launch. */
require_once __DIR__ . '/lib.php';
$me = current_admin();
if (!$me) { header('Location: login.php'); exit; }
$navActive = 'setup';
$pageTitle = 'Setup — ' . APP_NAME;
$steps = [
    ['event', '📅', 'Event & day', 'Who is playing and when'],
    ['questions', '📚', 'Questions', 'Banks and questions'],
    ['prizes', '🎁', 'Prizes', 'How winners are picked'],
    ['settings', '⚙️', 'Game settings', 'Title, timers, join link'],
    ['launch', '🚀', 'Launch', 'Open the game'],
];
?><!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/partials/head.php'; ?></head>
<body>
<div class="blobs"><i></i><i></i><i></i></div>
<?php include __DIR__ . '/partials/admin_top.php'; ?>

<div class="flex flex-col lg:flex-row gap-4 lg:gap-6 p-3 md:p-5 max-w-[1700px] mx-auto">
  <!-- ======================= step rail ======================= -->
  <aside class="lg:w-72 shrink-0">
    <div class="lg:sticky lg:top-24 space-y-3">
      <div class="glass p-2 flex lg:flex-col gap-1 overflow-x-auto custom-scrollbar">
        <?php foreach ($steps as $i => $s): ?>
          <a href="#<?= $s[0] ?>" data-nav="<?= $s[0] ?>" class="step-link flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 transition shrink-0">
            <span class="step-num w-9 h-9 rounded-xl grid place-items-center text-lg" style="background:rgba(255,255,255,.08)"><?= $s[1] ?></span>
            <span class="leading-tight"><span class="block font-display font-semibold"><span class="text-muted text-xs mr-1"><?= $i + 1 ?></span><?= $s[2] ?></span>
              <span class="hidden lg:block text-[11px] text-muted step-sub" data-sub="<?= $s[0] ?>"><?= $s[3] ?></span></span>
            <span class="ml-auto hidden step-ok" data-ok="<?= $s[0] ?>"><i class="fa-solid fa-circle-check text-emerald-400"></i></span>
          </a>
        <?php endforeach; ?>
        <div class="hidden lg:block divider my-1"></div>
        <a href="#players" data-nav="players" class="step-link flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 shrink-0">
          <span class="w-9 h-9 rounded-xl grid place-items-center text-lg" style="background:rgba(255,255,255,.08)">👥</span><span class="font-display font-semibold">Players &amp; winners</span></a>
        <a href="#org" data-nav="org" id="navOrg" class="hidden step-link flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 shrink-0">
          <span class="w-9 h-9 rounded-xl grid place-items-center text-lg" style="background:rgba(255,255,255,.08)">🏛️</span><span class="font-display font-semibold">Organization</span></a>
        <a href="#account" data-nav="account" class="step-link flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 shrink-0">
          <span class="w-9 h-9 rounded-xl grid place-items-center text-lg" style="background:rgba(255,255,255,.08)">👤</span><span class="font-display font-semibold">My account</span></a>
      </div>
      <div id="planCard" class="glass p-4 space-y-1.5 text-sm"></div>
      <div id="railLive" class="hidden glass p-4 space-y-2 text-center" style="border-color:rgba(34,197,94,.5)">
        <p class="chip chip-green mx-auto"><span class="live-dot"></span> Your game is live</p>
        <a href="live.php" class="btn btn-go w-full"><i class="fa-solid fa-tower-broadcast"></i> Open live control</a>
      </div>
    </div>
  </aside>

  <main class="flex-1 min-w-0 space-y-4 pb-16">

    <!-- ======================= 1. EVENT ======================= -->
    <section data-page="event" class="hidden space-y-4 rise-in">
      <div class="flex items-end justify-between flex-wrap gap-3">
        <div><p class="chip chip-pink">Step 1 of 5</p><h1 class="font-display text-4xl font-bold mt-2">Event &amp; day</h1>
          <p class="text-muted">Players join an <b class="text-white">event</b> once and keep their name, emoji and score for every day of it.</p></div>
      </div>
      <div class="grid xl:grid-cols-3 gap-4">
        <div class="card xl:col-span-2 space-y-4">
          <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[220px]"><label class="lbl">Event name</label><input id="evName" class="field !text-lg !font-semibold"></div>
            <button class="btn" onclick="A.renameEvent()"><i class="fa-solid fa-pen"></i> Rename</button>
          </div>
          <div>
            <p class="lbl">Days / sessions in this event</p>
            <div id="sessionList" class="flex flex-wrap gap-2"></div>
          </div>
          <div class="glass-2 p-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[180px]"><label class="lbl">Running the quiz again on a new day?</label><input id="newSessLabel" class="field" placeholder="e.g. Day 2"></div>
            <button class="btn btn-go" onclick="A.newSession()"><i class="fa-solid fa-sun"></i> Start new day (keep scores)</button>
          </div>
        </div>
        <div class="card space-y-3">
          <h3 class="font-display text-xl font-bold">All my events</h3>
          <div id="eventList" class="space-y-2 max-h-64 overflow-y-auto custom-scrollbar pr-1"></div>
          <div class="divider"></div>
          <label class="lbl">Start a brand-new event</label>
          <div class="flex gap-2"><input id="newEvName" class="field" placeholder="e.g. Spring Symposium"><button class="btn btn-primary" onclick="A.newEvent()">Create</button></div>
          <p class="help">A new event starts fresh: everyone joins again and scores start at zero. Old events are kept.</p>
        </div>
      </div>
      <div class="flex justify-end"><a href="#questions" class="btn btn-primary btn-lg">Next: Questions <i class="fa-solid fa-arrow-right"></i></a></div>
    </section>

    <!-- ======================= 2. QUESTIONS ======================= -->
    <section data-page="questions" class="hidden space-y-4 rise-in">
      <div><p class="chip chip-pink">Step 2 of 5</p><h1 class="font-display text-4xl font-bold mt-2">Questions</h1>
        <p class="text-muted">Every set you upload or paste is saved as a <b class="text-white">question bank</b>. Load the one you want to play.</p></div>

      <div class="card space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <span class="w-12 h-12 rounded-2xl grid place-items-center text-2xl shrink-0" style="background:linear-gradient(135deg,var(--gold),var(--orange))">📚</span>
            <div class="min-w-0"><p class="text-[11px] uppercase tracking-widest font-bold text-muted">Loaded now</p>
              <p class="font-display text-2xl font-bold truncate"><span id="curBankName"></span> <span id="curBankCount" class="text-muted text-base font-sans"></span></p></div>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="btn btn-sm" onclick="A.renameBank(A.currentBankId())"><i class="fa-solid fa-pen"></i> Rename</button>
            <button class="btn btn-sm" onclick="A.dupBank(A.currentBankId())"><i class="fa-solid fa-clone"></i> Save a copy</button>
          </div>
        </div>
        <div class="divider"></div>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="font-display text-lg font-bold">All my question banks</h3>
          <div class="flex flex-wrap items-center gap-2">
            <input id="bankSearch" class="field !w-44 !py-2" placeholder="Search banks…" oninput="A.renderBanks()">
            <label class="btn btn-sm cursor-pointer"><i class="fa-solid fa-file-arrow-up"></i> Upload JSON<input type="file" accept=".json,application/json" multiple class="hidden" onchange="A.loadFile(this)"></label>
            <button class="btn btn-sm" onclick="A.openJson('new')"><i class="fa-solid fa-paste"></i> Paste JSON</button>
            <button class="btn btn-sm btn-gold" onclick="A.newBank()"><i class="fa-solid fa-plus"></i> New empty bank</button>
          </div>
        </div>
        <div id="bankList" class="grid md:grid-cols-2 2xl:grid-cols-3 gap-3 max-h-[440px] overflow-y-auto custom-scrollbar pr-1"></div>
      </div>

      <div class="grid 2xl:grid-cols-5 gap-4 items-start">
        <div id="formHome" class="2xl:col-span-2 2xl:sticky 2xl:top-24">
        <div id="qForm" class="card space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 id="formTitle" class="font-display text-xl font-bold"><i class="fa-solid fa-plus-circle text-pink-400"></i> Add a question</h3>
            <div class="flex gap-2">
              <button class="btn btn-sm" onclick="A.downloadPpt()" title="Download the loaded bank as a PowerPoint deck"><i class="fa-solid fa-file-powerpoint text-orange-300"></i> PowerPoint</button>
              <button class="btn btn-sm" onclick="A.openJson('view')" title="View / edit the loaded bank as JSON"><i class="fa-solid fa-code"></i> JSON</button>
              <button class="btn btn-sm" onclick="A.exportJson()" title="Download the loaded bank"><i class="fa-solid fa-download"></i></button>
            </div>
          </div>
          <input type="hidden" id="editIdx" value="-1">
          <input type="hidden" id="fType" value="mc">
          <div>
            <label class="lbl">Question type</label>
            <div id="typePicker" class="grid grid-cols-3 sm:grid-cols-5 2xl:grid-cols-3 gap-1.5"></div>
            <p id="typeHelp" class="help"></p>
          </div>
          <div><label class="lbl" id="fTextLbl">Question</label><textarea id="fText" rows="2" class="field !text-base" placeholder="Type the question…"></textarea></div>

          <!-- multiple choice / poll -->
          <div data-for="mc poll" class="space-y-2.5">
            <div class="grid sm:grid-cols-2 gap-2.5">
              <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="flex items-center gap-2 rounded-xl p-1.5 pl-2 ans-<?= $i ?> <?= $i >= 4 ? 'more-opt hidden' : '' ?>" style="background:color-mix(in srgb, var(--c) 22%, transparent);border:1px solid color-mix(in srgb, var(--c) 55%, transparent)">
                  <i class="fa-solid <?= ['fa-play fa-rotate-270', 'fa-diamond', 'fa-circle', 'fa-square', 'fa-star', 'fa-heart'][$i] ?>" style="color:var(--c)"></i>
                  <input id="fO<?= $i ?>" class="field !bg-transparent !border-0 !p-1.5 !shadow-none" placeholder="Answer <?= chr(65 + $i) ?><?= $i > 1 ? ' (optional)' : '' ?>">
                </div>
              <?php endfor; ?>
            </div>
            <button type="button" class="btn btn-sm btn-ghost" onclick="document.querySelectorAll('.more-opt').forEach(e=>e.classList.remove('hidden'));this.remove()"><i class="fa-solid fa-plus"></i> More answers (up to 6)</button>
          </div>
          <div data-for="mc"><label class="lbl">Correct answer</label>
            <select id="fCorrect" class="field"><?php for ($i = 0; $i < 6; $i++): ?><option value="<?= $i ?>"><?= chr(65 + $i) ?></option><?php endfor; ?></select></div>
          <!-- true / false -->
          <div data-for="tf"><label class="lbl">The statement is…</label>
            <div class="grid grid-cols-2 gap-2"><label class="pick justify-center"><input type="radio" name="fTF" value="0" class="accent-emerald-400" checked> <b>True</b></label>
              <label class="pick justify-center"><input type="radio" name="fTF" value="1" class="accent-rose-400"> <b>False</b></label></div></div>
          <!-- type the answer -->
          <div data-for="typeans"><label class="lbl">Accepted answers (one per line — capitals, accents and punctuation don't matter)</label>
            <textarea id="fAccepted" rows="3" class="field" placeholder="Paris&#10;City of Paris"></textarea></div>
          <!-- closest number -->
          <div data-for="estimate" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div><label class="lbl">Correct number</label><input id="fAns" type="number" step="any" class="field"></div>
            <div><label class="lbl">Counts as correct within ±</label><input id="fTol" type="number" step="any" min="0" value="0" class="field"></div>
            <div><label class="lbl">Unit (optional)</label><input id="fUnit" maxlength="12" class="field" placeholder="e.g. %, years"></div>
            <div><label class="lbl">Slider from</label><input id="fMin" type="number" step="any" value="0" class="field"></div>
            <div><label class="lbl">Slider to</label><input id="fMax" type="number" step="any" value="100" class="field"></div>
            <div><label class="lbl">Step</label><input id="fStep" type="number" step="any" min="0" value="1" class="field"></div>
          </div>
          <!-- ranking -->
          <div data-for="ranking"><label class="lbl">Items in the CORRECT order, top to bottom (3–6 lines). Phones see them shuffled.</label>
            <textarea id="fItems" rows="4" class="field" placeholder="Mercury&#10;Venus&#10;Earth&#10;Mars"></textarea></div>
          <!-- rating -->
          <div data-for="rating" class="grid grid-cols-3 gap-3">
            <div><label class="lbl">Scale</label><select id="fScale" class="field"><option value="5">1 – 5</option><option value="10">1 – 10</option></select></div>
            <div><label class="lbl">Low label</label><input id="fLow" class="field" value="Not at all"></div>
            <div><label class="lbl">High label</label><input id="fHigh" class="field" value="Absolutely"></div>
          </div>
          <!-- word cloud -->
          <div data-for="wordcloud"><label class="lbl">Words each person can send</label>
            <select id="fMaxEntries" class="field"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select></div>
          <!-- idea board -->
          <div data-for="ideas" class="space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div><label class="lbl">Ideas per person</label><input id="fMaxIdeas" type="number" min="1" max="5" value="2" class="field"></div>
              <div><label class="lbl">Seconds to vote</label><input id="fVoteTime" type="number" min="10" max="600" value="60" class="field"></div>
              <div><label class="lbl">⭐ Nominations each</label><input id="fVotesPer" type="number" min="1" max="5" value="1" class="field"></div>
              <div><label class="lbl">👎 votes that remove an idea</label><input id="fRemoveAfter" type="number" min="0" max="100" value="3" class="field"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div><label class="lbl">🏅 Points for the best idea</label><input id="fBestPts" type="number" min="0" class="field" placeholder="Auto"></div>
              <div><label class="lbl">🗳️ Points for voting</label><input id="fVoterPts" type="number" min="0" class="field" placeholder="Auto"></div>
            </div>
            <p class="help !mt-0">Auto = the best idea earns about what a timed question in this bank is worth; everyone who votes (⭐ or 👎) earns a quarter of that. 0 removes an idea never.</p>
          </div>
          <!-- participation points for opinion questions -->
          <div data-for="poll rating wordcloud"><label class="lbl">Points just for taking part (optional)</label><input id="fPoints" type="number" min="0" max="1000" value="0" class="field !w-32"></div>

          <div class="grid grid-cols-2 gap-3">
            <div><label class="lbl" id="fTimeLbl">Seconds to answer</label><input id="fTime" type="number" min="5" max="600" value="15" class="field"></div>
            <div class="glass-2 p-2.5 space-y-2">
              <label class="flex items-center gap-2 cursor-pointer font-semibold text-sm"><span class="toggle"><input type="checkbox" id="fPrizeOn" checked onchange="A.prizeToggle()"><span></span></span> 🎁 Prize question</label>
              <select id="fPrize" class="field !py-1.5 !text-sm"></select>
            </div>
          </div>
          <div><label class="lbl">💬 Comment shown at the reveal <span class="text-[color:var(--dim)]">(optional)</span></label>
            <textarea id="fNote" rows="2" maxlength="400" class="field" placeholder="e.g. Fun fact: this was our 10th symposium!"></textarea></div>
          <div><label class="lbl">Name <span class="text-[color:var(--dim)]">(optional, only you see it — e.g. Tiebreaker)</span></label><input id="fName" maxlength="60" class="field" placeholder="Name it to pull it up fast during the game"></div>
          <div class="flex justify-end gap-2">
            <button id="cancelEdit" class="btn hidden" onclick="A.resetForm()">Cancel</button>
            <button class="btn btn-primary" onclick="A.saveQuestion()"><i class="fa-solid fa-floppy-disk"></i> Save question</button>
          </div>
        </div>
        </div>

        <div class="card space-y-3 2xl:col-span-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-display text-xl font-bold">In “<span id="qBankName"></span>” <span class="chip ml-1" id="qCount">0</span></h3>
            <div class="flex flex-wrap items-center gap-2">
              <input id="qSearch" class="field !w-48 !py-2" placeholder="Find by name or text…" oninput="A.renderQuestions()">
              <div class="flex items-center gap-1.5 glass-2 !rounded-xl px-2 py-1"><span class="text-xs text-muted font-bold">All timers</span>
                <input id="allTime" type="number" min="5" max="300" value="15" class="field !w-16 !py-1 !px-2"><button class="btn btn-sm" onclick="A.applyAllTime()">Set</button></div>
              <button class="btn btn-sm btn-danger" onclick="A.clearQuestions()"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>
          <div id="qList" class="space-y-2"></div>
        </div>
      </div>
      <div class="flex justify-between"><a href="#event" class="btn btn-lg btn-ghost"><i class="fa-solid fa-arrow-left"></i> Back</a><a href="#prizes" class="btn btn-primary btn-lg">Next: Prizes <i class="fa-solid fa-arrow-right"></i></a></div>
    </section>

    <!-- ======================= 3. PRIZES ======================= -->
    <section data-page="prizes" class="hidden space-y-4 rise-in">
      <div><p class="chip chip-pink">Step 3 of 5</p><h1 class="font-display text-4xl font-bold mt-2">Prizes</h1>
        <p class="text-muted">Choose how winners are picked. You can still click <b class="text-white">Winner!</b> any time during the game.</p></div>
      <div class="card space-y-5">
        <div class="flex flex-wrap items-start gap-4 glass-2 p-4">
          <label class="flex items-start gap-4 cursor-pointer flex-1 min-w-[240px]">
            <span class="toggle mt-1"><input type="checkbox" id="cPrizeEvery"><span></span></span>
            <span><span class="font-display text-lg font-bold">Give a prize after every question</span>
              <span class="block text-sm text-muted">A winner is drawn automatically a few seconds after each answer is revealed.</span></span>
          </label>
          <div class="shrink-0"><label class="lbl" for="cDelay">Seconds after reveal</label><input id="cDelay" type="number" min="1" max="60" class="field !w-24"></div>
        </div>
        <div>
          <p class="lbl">Default prize type</p>
          <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-3" id="modeCards"></div>
        </div>
        <div id="mixBox"><label class="lbl">When mixing, switch prize types…</label>
          <select id="cMixStyle" class="field md:!w-auto"><option value="rotate">In order: random correct → random player → fastest → repeat</option><option value="random">Randomly each question</option></select></div>
        <div class="grid md:grid-cols-2 gap-4">
          <div><label class="lbl">Can someone win more than one question prize?</label>
            <select id="cRepeat" class="field"><option value="event">No — one question prize per person for the whole event</option><option value="session">One question prize per person per day</option><option value="allow">Yes — anyone can win again</option></select>
            <p class="help !text-amber-100/80">🏆 This only limits the per-question prize draws. The <b>Grand Podium</b> always shows the top scores, so people who already won a question prize can still finish on the podium.</p></div>
          <label class="flex items-start gap-3 cursor-pointer glass-2 p-3">
            <span class="toggle mt-0.5"><input type="checkbox" id="cAnnounce"><span></span></span>
            <span><span class="font-bold">Announce the prize type during each question</span><span class="block text-xs text-muted">Shows “Prize: Fastest correct answer” on screens and phones.</span></span>
          </label>
        </div>
        <p class="help">Tip: set a different prize on a single question from the Questions step.</p>
      </div>
      <div class="flex justify-between"><a href="#questions" class="btn btn-lg btn-ghost"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <button class="btn btn-primary btn-lg" onclick="A.saveSettings('#settings')">Save &amp; next <i class="fa-solid fa-arrow-right"></i></button></div>
    </section>

    <!-- ======================= 4. SETTINGS ======================= -->
    <section data-page="settings" class="hidden space-y-4 rise-in">
      <div><p class="chip chip-pink">Step 4 of 5</p><h1 class="font-display text-4xl font-bold mt-2">Game settings</h1>
        <p class="text-muted">How the game looks and how phones reach it.</p></div>
      <div class="grid xl:grid-cols-2 gap-4">
        <div class="card space-y-4">
          <h3 class="font-display text-xl font-bold">🎨 Look &amp; scoring</h3>
          <div><label class="lbl">Game title (shown on every screen)</label><input id="cTitle" class="field !text-lg !font-semibold"></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="lbl">Default seconds per question</label><input id="cDefTime" type="number" min="5" max="300" class="field"></div>
            <div><label class="lbl">Hide rankings for the last N questions</label><input id="cLock" type="number" min="0" max="20" class="field"></div>
          </div>
          <div><label class="lbl">Leaderboard &amp; podium show…</label>
            <select id="cScope" class="field"><option value="event">Event total (all days added together)</option><option value="session">Today only</option></select></div>
          <label class="flex items-center gap-3 cursor-pointer"><span class="toggle"><input type="checkbox" id="cAutoEnd"><span></span></span><span class="font-semibold">End a question early when everyone has answered</span></label>
          <div>
            <label class="flex items-center gap-3 cursor-pointer"><span class="toggle"><input type="checkbox" id="cSignupLink"><span></span></span><span class="font-semibold">Show “Host your own quiz” link at the end <span class="chip chip-gold !py-0 !px-1.5 !text-[10px]">Pro to hide</span></span></label>
            <p class="help" id="cSignupHelp">At the podium, players see a link to create their own free RizQuiz account.</p>
          </div>
        </div>
        <div class="card space-y-4">
          <h3 class="font-display text-xl font-bold">📱 How phones join</h3>
          <div class="glass-2 p-3 space-y-1">
            <p class="text-[11px] uppercase tracking-widest font-bold text-muted">Your player link</p>
            <p id="longLink" class="font-semibold text-cyan-200 break-all"></p>
            <p class="help !mt-1">Your own folder on this server — the QR code on your Live screen points here.</p>
          </div>
          <div><label class="lbl">Web address of this RizQuiz server (leave blank to detect automatically)</label>
            <input id="cJoinUrl" class="field" placeholder="e.g. http://192.168.1.25/rizquiz/ or https://quiz.mycollege.edu/">
            <p id="joinHint" class="help"></p></div>
          <div class="glass-2 p-3 space-y-3">
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <p class="font-display text-lg font-bold">🔗 Short link</p>
              <button class="btn btn-sm btn-gold" onclick="A.makeShort()"><i class="fa-solid fa-wand-magic-sparkles"></i> Make a short link</button>
            </div>
            <div><label class="lbl">Short link (made here, or paste one from bit.ly / tinyurl.com that points to your player link)</label>
              <input id="cShortUrl" class="field" placeholder="https://is.gd/abc123"></div>
            <label class="flex items-center gap-3 cursor-pointer"><span class="toggle"><input type="checkbox" id="cUseShort"><span></span></span><span class="font-semibold">Use the short link in the QR code and on screen</span></label>
            <p class="help">A shorter link makes a simpler QR code and is easier to type. Short links need internet access on players' phones.</p>
          </div>
          <div><label class="lbl">A player counts as “here” if their phone checked in within (seconds)</label><input id="cActive" type="number" min="5" max="300" class="field !w-28"></div>
          <div class="glass-2 p-3 space-y-3">
            <p class="font-display text-lg font-bold">📶 Event Wi-Fi (optional)</p>
            <p class="help !mt-0">Fill this in and the lobby shows a second QR code that connects phones to your Wi-Fi with one scan — best for big events where cell data is weak.</p>
            <div class="grid sm:grid-cols-2 gap-3">
              <div><label class="lbl">Network name</label><input id="cWifiName" class="field" placeholder="e.g. RizQuiz-Event" autocomplete="off"></div>
              <div><label class="lbl">Password (blank if open)</label><input id="cWifiPass" class="field" autocomplete="off"></div>
            </div>
          </div>
          <label class="flex items-center gap-3 cursor-pointer"><span class="toggle"><input type="checkbox" id="cWifi"><span></span></span><span class="font-semibold">Show a network note on the join screens</span></label>
          <div><label class="lbl">Network note</label><textarea id="cWifiText" rows="2" class="field"></textarea></div>
        </div>
      </div>
      <div class="grid xl:grid-cols-2 gap-4">
        <div class="card space-y-4">
          <div class="flex items-center justify-between gap-2"><h3 class="font-display text-xl font-bold">🎨 Branding</h3><span class="chip chip-gold">Pro</span></div>
          <div id="brandLocked" class="hidden glass-2 p-4 text-sm"></div>
          <div id="brandEdit" class="space-y-4">
            <div class="flex items-center gap-4 flex-wrap">
              <div id="logoPreview" class="w-24 h-24 rounded-2xl bg-white grid place-items-center overflow-hidden text-3xl text-slate-400">⚡</div>
              <div class="space-y-2">
                <label class="btn btn-sm cursor-pointer"><i class="fa-solid fa-image"></i> Upload logo<input type="file" accept="image/png,image/jpeg,image/gif,image/webp" class="hidden" onchange="A.uploadLogo(this)"></label>
                <button class="btn btn-sm btn-ghost" onclick="A.removeLogo()">Remove logo</button>
                <p class="help !mt-0">PNG, JPG, GIF or WebP, under 1 MB. A square logo looks best.</p>
              </div>
            </div>
            <div>
              <p class="lbl">Color scheme</p>
              <div id="palettes" class="flex flex-wrap gap-2 mb-3"></div>
              <div class="grid grid-cols-3 gap-3">
                <label class="glass-2 p-2 text-xs font-semibold flex flex-col gap-1">Main color<input id="bPrimary" type="color" class="w-full h-10 rounded-lg bg-transparent cursor-pointer" oninput="A.previewBrand()"></label>
                <label class="glass-2 p-2 text-xs font-semibold flex flex-col gap-1">Second color<input id="bAccent" type="color" class="w-full h-10 rounded-lg bg-transparent cursor-pointer" oninput="A.previewBrand()"></label>
                <label class="glass-2 p-2 text-xs font-semibold flex flex-col gap-1">Background<input id="bBg" type="color" class="w-full h-10 rounded-lg bg-transparent cursor-pointer" oninput="A.previewBrand()"></label>
              </div>
            </div>
            <div class="flex gap-2"><button class="btn btn-primary" onclick="A.saveBrand()"><i class="fa-solid fa-floppy-disk"></i> Save branding</button>
              <button class="btn btn-ghost" onclick="A.resetBrand()">Use RizQuiz colors</button></div>
            <p class="help !mt-0">Your logo and colors appear on the big screen, the live page and every player's phone.</p>
          </div>
        </div>
        <div class="card space-y-4">
          <h3 class="font-display text-xl font-bold">🎵 Music</h3>
          <p class="help !mt-0">Any audio file in the RizQuiz folder (or its <b>sounds</b> folder) whose name starts with <b>Question</b> or <b>Podium</b> shows up here.</p>
          <div><label class="lbl">Music while a question is open</label>
            <div class="flex gap-2"><select id="cQSound" class="field"></select><button class="btn" onclick="A.previewSound('cQSound')" title="Listen"><i class="fa-solid fa-play"></i></button></div></div>
          <div><label class="lbl">Music for the Grand Podium</label>
            <div class="flex gap-2"><select id="cPSound" class="field"></select><button class="btn" onclick="A.previewSound('cPSound')" title="Listen"><i class="fa-solid fa-play"></i></button></div></div>
        </div>
      </div>
      <div class="flex justify-between"><a href="#prizes" class="btn btn-lg btn-ghost"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <button class="btn btn-primary btn-lg" onclick="A.saveSettings('#launch')">Save &amp; next <i class="fa-solid fa-arrow-right"></i></button></div>
    </section>

    <!-- ======================= 5. LAUNCH ======================= -->
    <section data-page="launch" class="hidden space-y-4 rise-in">
      <div><p class="chip chip-pink">Step 5 of 5</p><h1 class="font-display text-4xl font-bold mt-2">Ready for lift-off? 🚀</h1>
        <p class="text-muted">Check the list, then launch. Launching opens the game and shows the QR code for players.</p></div>
      <div class="grid xl:grid-cols-5 gap-4 items-start">
        <div class="card xl:col-span-3 space-y-2" id="checklist"></div>
        <div class="card xl:col-span-2 text-center space-y-4 p-8" id="launchBox">
          <div class="text-7xl bob">🚀</div>
          <div id="launchState"></div>
          <button id="launchBtn" class="btn btn-go btn-xl w-full" onclick="A.launch()"><i class="fa-solid fa-rocket"></i> Launch game</button>
          <button id="closeBtn" class="btn btn-danger w-full hidden" onclick="A.closeGame()"><i class="fa-solid fa-power-off"></i> Close game (stop joining)</button>
          <p class="help">Tip: open the live screen on the projector, press <b>F</b> for full screen.</p>
        </div>
      </div>
    </section>

    <!-- ======================= PLAYERS ======================= -->
    <section data-page="players" class="hidden space-y-4 rise-in">
      <div><h1 class="font-display text-4xl font-bold">Players &amp; winners</h1><p class="text-muted">Everyone in <b class="text-white" id="plEvent"></b>, their scores and prizes.</p></div>
      <div class="card space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="font-display text-xl font-bold">Players <span class="chip" id="pCount">0</span> <span id="pOnline" class="chip chip-green"></span></h3>
          <div class="flex flex-wrap gap-2">
            <input id="pSearch" class="field !w-44 !py-2" placeholder="Search…" oninput="A.renderPlayers()">
            <button class="btn btn-sm btn-go" onclick="A.downloadExcel()"><i class="fa-solid fa-file-excel"></i> Download Excel <span class="chip chip-gold !py-0 !px-1.5 !text-[10px]">Pro</span></button>
            <button class="btn btn-sm" onclick="A.exportCsv('export_csv')"><i class="fa-solid fa-file-csv"></i> Standings CSV</button>
            <button class="btn btn-sm btn-danger" onclick="A.resetScores()">Reset scores</button>
            <button class="btn btn-sm btn-danger !bg-rose-600/80 !text-white" onclick="A.resetGame()" title="Delete all players and their data"><i class="fa-solid fa-triangle-exclamation"></i> Reset game</button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-muted uppercase text-[11px] tracking-wider text-left"><tr>
              <th class="p-2">#</th><th class="p-2">Player</th><th class="p-2 text-right">Event total</th><th class="p-2 text-right">Today</th><th class="p-2 text-right">Correct</th><th class="p-2 text-right">Prizes</th><th class="p-2">Rejoin code</th><th class="p-2"></th></tr></thead>
            <tbody id="pTable"></tbody>
          </table>
        </div>
        <div id="blockedBox" class="hidden space-y-2 pt-2 border-t border-white/10">
          <p class="lbl !mb-0">🚫 Blocked names <span class="font-normal text-muted normal-case tracking-normal">— nobody can join with these. Click × to allow one again.</span></p>
          <div id="blockedList" class="flex flex-wrap gap-2"></div>
        </div>
      </div>
      <div class="card space-y-3">
        <div class="flex items-center justify-between"><h3 class="font-display text-xl font-bold">🏆 Prize winners</h3>
          <button class="btn btn-sm" onclick="A.exportCsv('export_winners')"><i class="fa-solid fa-file-csv"></i> Winners CSV</button></div>
        <div id="winLog" class="space-y-2 max-h-96 overflow-y-auto custom-scrollbar pr-1"></div>
      </div>
    </section>

    <!-- ======================= ACCOUNT ======================= -->
    <section data-page="org" class="hidden space-y-4 rise-in">
      <div><p class="chip chip-gold">Enterprise</p><h1 class="font-display text-4xl font-bold mt-2">Organization users</h1>
        <p class="text-muted">Everyone you add here runs their own quizzes with their own QR code, under your organization's plan and official branding.</p></div>
      <div class="grid xl:grid-cols-3 gap-4 items-start">
        <div class="card space-y-3">
          <h3 class="font-display text-xl font-bold">➕ Add a user</h3>
          <div><label class="lbl">Name</label><input id="tName" class="field"></div>
          <div><label class="lbl">Username or email</label><input id="tUser" class="field" autocomplete="off"></div>
          <div><label class="lbl">Web name (their QR link)</label><input id="tHandle" class="field" placeholder="e.g. prof-lee"></div>
          <div><label class="lbl">Temporary password</label><input id="tPw" class="field font-mono" autocomplete="off"></div>
          <button class="btn btn-primary w-full" onclick="A.teamAdd()"><i class="fa-solid fa-user-plus"></i> Add user</button>
          <p class="help">They choose their own password and set up an authenticator app the first time they sign in.</p>
        </div>
        <div class="card xl:col-span-2 space-y-3"><h3 class="font-display text-xl font-bold">👥 Users</h3><div id="teamList" class="grid md:grid-cols-2 gap-3"></div></div>
      </div>
    </section>

    <section data-page="account" class="hidden space-y-4 rise-in">
      <div><h1 class="font-display text-4xl font-bold">My account</h1></div>
      <div class="grid xl:grid-cols-3 gap-4 items-start">
        <div class="card space-y-3" id="acctCard"></div>
        <div class="card space-y-3">
          <h3 class="font-display text-xl font-bold">🔑 Change password</h3>
          <div><label class="lbl">Current password</label><input id="aCur" type="password" class="field" autocomplete="current-password"></div>
          <div><label class="lbl">New password</label><input id="aNew" type="password" class="field" autocomplete="new-password"></div>
          <div><label class="lbl">Repeat new password</label><input id="aNew2" type="password" class="field" autocomplete="new-password"></div>
          <button class="btn btn-primary w-full" onclick="A.changePassword()">Update password</button>
        </div>
        <div class="card space-y-3">
          <h3 class="font-display text-xl font-bold">🛡️ Two-step sign-in</h3>
          <p class="text-sm text-muted">Always on for every admin. Backup codes left: <b class="text-white" id="aBackupLeft"></b></p>
          <div><label class="lbl">Current 6-digit code</label><input id="aCode" class="field code-input" maxlength="6" inputmode="numeric"></div>
          <button class="btn w-full" onclick="A.newBackupCodes()">Make new backup codes</button>
          <div id="aCodes" class="grid grid-cols-2 gap-2 font-mono font-bold text-center"></div>
        </div>
      </div>
    </section>
  </main>
</div>

<!-- JSON modal -->
<div id="jsonModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" style="background:rgba(5,4,20,.75);backdrop-filter:blur(6px)">
  <div class="glass w-full max-w-3xl p-6 space-y-4" style="background:rgba(20,15,55,.96)">
    <div class="flex items-center justify-between"><h3 class="font-display text-2xl font-bold"><i class="fa-solid fa-code text-pink-400"></i> Question JSON</h3>
      <button onclick="A.closeJson()" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark text-lg"></i></button></div>
    <p class="text-sm text-muted">Format: <code class="text-emerald-300">[{"name":"Tiebreaker","text":"…","options":["A","B","C","D"],"correctIndex":0,"timeLimit":15}]</code></p>
    <textarea id="jsonText" rows="12" class="field font-mono !text-emerald-300 !text-sm"></textarea>
    <div><label class="lbl">Name for a new bank</label><input id="jsonBankName" class="field" placeholder="e.g. Spring Symposium 2027"></div>
    <div class="flex flex-wrap items-center justify-between gap-2">
      <button onclick="navigator.clipboard.writeText(document.getElementById('jsonText').value); RQ.toast('Copied!','ok')" class="btn"><i class="fa-solid fa-copy"></i> Copy</button>
      <div class="flex flex-wrap gap-2">
        <button onclick="A.applyJson('append')" class="btn">Add to loaded bank</button>
        <button onclick="A.applyJson('replace')" class="btn">Replace loaded bank</button>
        <button onclick="A.applyJson('new')" class="btn btn-primary"><i class="fa-solid fa-layer-group"></i> Save as new bank &amp; load it</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/vendor/xlsx.mini.min.js" defer></script>
<script src="assets/vendor/pptxgen.bundle.js" defer></script>
<script src="assets/qtypes.js?v=6"></script>
<script src="assets/setup.js?v=9"></script>
</body>
</html>
