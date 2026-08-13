<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Session — Sparring</title>
<link rel="stylesheet" href="assets/input.css">
</head>
<body>
<main>
  <header id="top-bar">
    <div id="session-title">Untitled</div>
    <div id="top-bar-trailing">
      <button id="new-session-btn" type="button">End session</button>
      <button id="avatar-btn" type="button" aria-haspopup="true" aria-expanded="false" hidden></button>
      <div id="avatar-popover" hidden role="dialog">You are <strong id="avatar-alias"></strong></div>
    </div>
  </header>

  <div id="history" aria-live="polite"></div>

  <!-- Playbook: visible from screen load (independent of #retention below —
       the two can be on screen at once), auto-dismissed on the first sent
       message. No dismiss control by design. -->
  <div id="playbook">
    <div id="playbook-shape" aria-hidden="true"></div>
    <div id="playbook-body">
      <h2 id="playbook-heading">Playbook</h2>
      <ol id="playbook-rules">
        <li><span class="playbook-bullet" aria-hidden="true">1</span><p>Start with a provoking position or scenario.</p></li>
        <li><span class="playbook-bullet" aria-hidden="true">2</span><p>Elaborate your argument in <strong>16 turns or less</strong>.</p></li>
        <li><span class="playbook-bullet" aria-hidden="true">3</span><p>There&rsquo;s no winning or losing — only progress.</p></li>
      </ol>
    </div>
  </div>

  <div id="retention" hidden>
    <p>Taking part requires agreeing to the Terms of Service and Privacy Policy
       below. The other two are your choice — leave either unchecked if you'd
       rather not, and you can still take part.</p>
    <label class="consent-row">
      <input type="checkbox" id="consent-tos">
      <span>I agree to the <a href="terms.php" target="_blank" rel="noopener">Terms of Service</a>
      and <a href="privacy.php" target="_blank" rel="noopener">Privacy Policy</a>.</span>
    </label>
    <label class="consent-row">
      <input type="checkbox" id="consent-projection">
      <span>I agree that my exchanged messages may be displayed on the projector.</span>
    </label>
    <label class="consent-row">
      <input type="checkbox" id="consent-retention">
      <span>I agree that my session may be collected and analyzed for this thesis.</span>
    </label>
    <button id="consent-confirm" type="button" disabled>Confirm</button>
  </div>

  <form id="composer">
    <div id="composer-row">
      <textarea id="contribution" placeholder="What do you want to work on?" disabled></textarea>
      <button id="submit" type="submit" disabled aria-label="Send">
        <span class="icon icon--glove" aria-hidden="true"></span>
      </button>
    </div>
    <div id="composer-footer">
      <p id="composer-disclaimer">Sparring is AI and can make mistakes</p>
      <div id="char-remaining"></div>
    </div>
  </form>
</main>
<div id="title-card" hidden aria-hidden="true">
  <span class="title-card__line title-card__line--slide">READY?</span>
  <span class="title-card__line title-card__line--slide">GET SET.</span>
  <span class="title-card__line title-card__line--grow">SPAR!</span>
</div>
<script>
window.CONTRIBUTION_MAX_CHARS = <?= (int) CONTRIBUTION_MAX_CHARS ?>;
window.SE01_WAIT_BOUND_MS = <?= (int) (SE01_WAIT_BOUND_SECONDS * 1000) ?>;
window.JUICY = {
    enabled: <?= JUICY_ENABLED ? 'true' : 'false' ?>,
    punch: <?= JUICY_PUNCH ? 'true' : 'false' ?>,
    titleCard: <?= JUICY_TITLE_CARD ? 'true' : 'false' ?>,
    wiggle: <?= JUICY_WIGGLE ? 'true' : 'false' ?>,
    sound: <?= JUICY_SOUND ? 'true' : 'false' ?>
};
</script>
<script src="assets/identity.js"></script>
<script src="assets/juicy.js"></script>
<script src="assets/zzfx.min.js"></script>
<script src="assets/sfx.js"></script>
<script src="assets/particles.js"></script>
<script src="assets/input.js"></script>
</body>
</html>
