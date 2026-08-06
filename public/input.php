<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sparring</title>
<link rel="stylesheet" href="assets/input.css">
</head>
<body>
<main>
  <div id="history" aria-live="polite"></div>

  <div id="retention" hidden>
    <p>Your exchanges may be shown on the wall in this room and kept for review after the exhibition.
       Nothing identifying you is shown or kept — keep it anyway?</p>
    <div class="retention-actions">
      <button id="retain-no" type="button">Don't keep it</button>
      <button id="retain-yes" type="button">Keep it</button>
    </div>
  </div>

  <form id="composer">
    <textarea id="contribution" placeholder="What do you want to work on?" disabled></textarea>
    <div id="char-remaining"></div>
    <div id="status" role="status"></div>
    <button id="submit" type="submit" disabled>Send</button>
  </form>
</main>
<script>
window.CONTRIBUTION_MAX_CHARS = <?= (int) CONTRIBUTION_MAX_CHARS ?>;
window.SE01_WAIT_BOUND_MS = <?= (int) (SE01_WAIT_BOUND_SECONDS * 1000) ?>;
</script>
<script src="assets/input.js"></script>
</body>
</html>
