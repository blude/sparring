<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Projection — Sparring</title>
<link rel="stylesheet" href="assets/display.css">
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
<script src="assets/identity.js"></script>
<script src="assets/juicy.js"></script>
<script src="assets/display.js"></script>
</body>
</html>
