<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Sparring — projection</title>
<link rel="stylesheet" href="assets/display.css">
</head>
<body>
<main id="wall" aria-live="off"></main>
<script>window.POLL_INTERVAL_MS = <?= (int) (DISPLAY_POLL_INTERVAL_SECONDS * 1000) ?>;</script>
<script src="assets/display.js"></script>
</body>
</html>
