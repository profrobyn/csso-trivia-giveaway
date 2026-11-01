<?php /* Shared <head> for all RizQuiz pages. Set $pageTitle before including. */ ?>
<meta charset="UTF-8">
<?php if (defined('RQ_HOST_DIR')): ?><base href="../"><?php endif; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0b0820">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<title><?= htmlspecialchars($pageTitle ?? 'RizQuiz') ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">
<!-- Everything is served from this server (no internet needed), so phones work on a local Wi-Fi network. -->
<link rel="stylesheet" href="assets/vendor/fonts.css">
<link rel="stylesheet" href="assets/vendor/tailwind.css?v=9">
<link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
<script src="assets/vendor/confetti.js"></script>
<script src="assets/vendor/qrcode.min.js"></script>
<link rel="stylesheet" href="assets/app.css?v=8">
<script src="assets/common.js?v=9"></script>
