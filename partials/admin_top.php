<?php
/* Top bar for signed-in pages. Needs $me (admin row) and $navActive ('setup' | 'live' | 'master'). */
$st = get_state();
?>
<header class="sticky top-0 z-40 px-3 md:px-5 py-2.5" style="background:rgba(11,8,32,.72);backdrop-filter:blur(16px);border-bottom:1px solid var(--line)">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div class="flex items-center gap-4">
      <a href="setup.php" class="logo"><span class="logo-badge"><span>⚡</span></span><span><?= htmlspecialchars(APP_NAME) ?></span></a>
      <nav class="flex items-center gap-1 glass-2 !rounded-xl p-1">
        <a href="setup.php" class="btn btn-sm <?= $navActive === 'setup' ? 'btn-primary' : 'btn-ghost' ?>"><i class="fa-solid fa-sliders"></i> Setup</a>
        <a href="live.php" class="btn btn-sm <?= $navActive === 'live' ? 'btn-primary' : 'btn-ghost' ?>" id="navLive">
          <?php if (!empty($st['launched'])): ?><span class="live-dot"></span><?php else: ?><i class="fa-solid fa-tower-broadcast"></i><?php endif; ?> Live</a>
        <?php if (is_master($me)): ?>
          <a href="master.php" class="btn btn-sm <?= $navActive === 'master' ? 'btn-primary' : 'btn-ghost' ?>"><i class="fa-solid fa-crown"></i> Accounts</a>
          <a href="billing.php" class="btn btn-sm <?= $navActive === 'billing' ? 'btn-primary' : 'btn-ghost' ?>"><i class="fa-solid fa-file-invoice-dollar"></i> Billing</a>
        <?php endif; ?>
      </nav>
    </div>
    <div class="flex items-center gap-2">
      <span class="chip chip-cyan hidden sm:inline-flex" title="Your game code — the QR code on the Live screen uses it"><i class="fa-solid fa-hashtag"></i> <?= htmlspecialchars(strtoupper($me['slug'])) ?></span>
      <a href="setup.php#account" class="chip hover:bg-white/15" title="My account">
        <span class="w-6 h-6 rounded-full grid place-items-center text-[11px] font-bold" style="background:linear-gradient(135deg,var(--pink),var(--violet))"><?= htmlspecialchars(strtoupper(mb_substr_safe($me['display_name'] ?: $me['username'], 1))) ?></span>
        <span class="hidden md:inline"><?= htmlspecialchars($me['display_name']) ?></span>
        <?php if (is_master($me)): ?><span class="chip chip-gold !py-0 !px-1.5 !text-[10px]">MASTER</span><?php endif; ?>
      </a>
      <button onclick="signOut()" class="btn btn-sm btn-ghost" title="Sign out"><i class="fa-solid fa-right-from-bracket"></i></button>
    </div>
  </div>
</header>
<script>
  window.RQ_CSRF = <?= json_encode($_SESSION['csrf']) ?>;
  async function signOut() { await RQ.api('logout'); location.href = 'login.php'; }
</script>
