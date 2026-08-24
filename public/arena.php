<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no, maximum-scale=1">
<title><?= t('arena.title') ?></title>
<link rel="stylesheet" href="<?= fasset('arena.css') ?>">
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
<link rel="manifest" href="/arena.webmanifest">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#d32f2f">
</head>
<body>
<div class="gradient-top" aria-hidden="true"></div>
<div class="header">
  <div id="logo"><h1 class="wordmark">Sparring</h1></div>
  <div id="stats">
    <time id="clock"></time>
    <div id="exchange-count"></div>
  </div>
</div>
<main id="wall" aria-live="off"></main>
<?= sillyBanner() ?>
<script>
window.POLL_INTERVAL_MS = <?= (int) (DISPLAY_POLL_INTERVAL_SECONDS * 1000) ?>;
window.DISPLAY_COLUMNS = <?= (int) DISPLAY_COLUMNS ?>;
window.DISPLAY_ITEM_LIMIT = <?= (int) DISPLAY_ITEM_LIMIT ?>;
window.LOCALE = <?= json_encode(resolve_locale()) ?>;
window.REPLY_COUNT_LABEL = <?= json_encode(t('arena.js.replyCount')) ?>;
window.STATS_LABEL = <?= json_encode(t('arena.js.stats')) ?>;
window.JUICY = {
    enabled: <?= JUICY_ENABLED ? 'true' : 'false' ?>,
    displayEntrance: <?= JUICY_DISPLAY_ENTRANCE ? 'true' : 'false' ?>
};
</script>
<script src="<?= fasset('identity.js') ?>" defer></script>
<script src="<?= fasset('juicy.js') ?>" defer></script>
<script src="<?= fasset('vendor/qrcode-generator.min.js') ?>" defer></script>
<script src="<?= fasset('qr-render.js') ?>" defer></script>
<script src="<?= fasset('arena.js') ?>" defer></script>
</body>
</html>
