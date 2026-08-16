<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Projection — Sparring</title>
<link rel="stylesheet" href="<?= fasset('display.css') ?>">
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
</head>
<body>
<h1 id="logo">Sparring</h1>
<main id="wall" aria-live="off"></main>
<script>window.POLL_INTERVAL_MS = <?= (int) (DISPLAY_POLL_INTERVAL_SECONDS * 1000) ?>;</script>
<script>window.DISPLAY_COLUMNS = <?= (int) DISPLAY_COLUMNS ?>;</script>
<script>window.DISPLAY_ITEM_LIMIT = <?= (int) DISPLAY_ITEM_LIMIT ?>;</script>
<script>
window.JUICY = {
    enabled: <?= JUICY_ENABLED ? 'true' : 'false' ?>,
    displayEntrance: <?= JUICY_DISPLAY_ENTRANCE ? 'true' : 'false' ?>
};
</script>
<script src="<?= fasset('identity.js') ?>"></script>
<script src="<?= fasset('juicy.js') ?>"></script>
<script src="<?= fasset('display.js') ?>"></script>
</body>
</html>
