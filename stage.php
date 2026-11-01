<?php
/* Projector / big-screen view: stage.php?g=GAMECODE. Read-only — it follows whatever the host does on live.php. */
require_once __DIR__ . '/lib.php';
$g = strtolower(preg_replace('/[^a-z0-9-]/i', '', $_GET['g'] ?? ''));
$pageTitle = APP_NAME . ' — Stage';
?><!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/partials/head.php'; ?></head>
<body class="overflow-hidden">
  <div class="blobs"><i></i><i></i><i></i></div>
  <div class="fixed top-3 right-3 z-40 flex items-center gap-2 opacity-20 hover:opacity-100 transition">
    <div id="volBox"></div>
    <button onclick="document.documentElement.requestFullscreen && document.documentElement.requestFullscreen()" class="btn btn-sm" title="Fullscreen"><i class="fa-solid fa-expand"></i></button>
  </div>
  <main style="height:100vh;height:100dvh"><?php include __DIR__ . '/partials/stage_markup.php'; ?></main>

  <div id="soundGate" class="fixed inset-0 z-50 flex items-center justify-center p-6" style="background:rgba(11,8,32,.85);backdrop-filter:blur(8px)">
    <div class="glass p-8 text-center space-y-4 max-w-md">
      <div class="text-6xl">📺</div>
      <h1 class="font-display text-3xl font-bold">Projector view</h1>
      <p class="text-muted">Click to start. This lets the music play on this screen.</p>
      <button class="btn btn-primary btn-lg w-full" onclick="document.getElementById('soundGate').remove(); document.documentElement.requestFullscreen && document.documentElement.requestFullscreen().catch(()=>{})"><i class="fa-solid fa-play"></i> Start display</button>
    </div>
  </div>

  <script src="assets/qtypes.js?v=6"></script>
  <script src="assets/stage.js?v=10"></script>
  <script>
    RQ.game = <?= json_encode($g) ?>;
    RQ.audio = RQ.createAudio('stage');
    document.getElementById('volBox').innerHTML = RQ.volumeControlsHtml();
    RQ.audio.apply();
    RQStage.init({ audio: true, onError: r => { if (r.code === 'NO_GAME') document.getElementById('stageRoot').innerHTML = '<div class="h-full grid place-items-center text-center"><div><div class="text-7xl mb-4">🔎</div><h1 class="font-display text-4xl font-bold">Game not found</h1><p class="text-muted mt-2">Open this page from the host\'s Live screen.</p></div></div>'; } });
  </script>
</body>
</html>
