<?php
declare(strict_types=1);
$openingMessage = resolve_opening_message($_GET['o'] ?? null);
?>
<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= t('dojo.title') ?></title>
<?= ogTags('/dojo', t('dojo.title'), t('dojo.ogDescription')) ?>
<link rel="stylesheet" href="<?= fasset('dojo.css') ?>">
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
<link rel="manifest" href="/dojo.webmanifest">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#d32f2f">
</head>
<body>
<main>
  <header id="top-bar">
    <div id="top-bar-leading">
      <button id="avatar-btn" type="button" aria-haspopup="true" aria-expanded="false" hidden></button>
      <div id="avatar-popover" hidden role="dialog"><?= t('dojo.avatarPopoverPrefix') ?><strong id="avatar-alias"></strong></div>
      <div id="session-title"><?= t('dojo.untitled') ?></div>
    </div>
    <div id="top-bar-trailing">
      <?= localeSwitcher() ?>
      <button id="new-session-btn" type="button"><?= t('dojo.endSession') ?></button>
    </div>
  </header>

  <div id="history" aria-live="polite"></div>

  <?php
    /* Playbook: visible from screen load (independent of #retention below —
      the two can be on screen at once), auto-dismissed on the first sent
      message. No dismiss control by design. */
  ?>
  <div id="playbook">
    <div id="playbook-shape-1" aria-hidden="true"></div>
    <div id="playbook-shape-2" aria-hidden="true"></div>
    <div id="playbook-body">
      <h2 id="playbook-heading"><?= t('dojo.playbook.heading') ?></h2>
      <ol id="playbook-rules">
        <li><span class="playbook-bullet" aria-hidden="true">1</span><p><?= t('dojo.playbook.rule1') ?></p></li>
        <li><span class="playbook-bullet" aria-hidden="true">2</span><p><?= t('dojo.playbook.rule2') ?></p></li>
        <li><span class="playbook-bullet" aria-hidden="true">3</span><p><?= t('dojo.playbook.rule3') ?></p></li>
      </ol>
    </div>
  </div>

  <div id="retention" hidden>
    <h2 id="consent-heading"><?= t('dojo.consent.heading') ?></h2>
    <p><?= t('dojo.consent.tosLine') ?></p>
    <label class="consent-row">
      <input type="checkbox" id="consent-tos">
      <span><?= t('dojo.consent.participateLabel') ?></span>
    </label>
    <p id="consent-optional-heading"><?= t('dojo.consent.optionalHeading') ?></p>
    <label class="consent-row">
      <input type="checkbox" id="consent-projection">
      <span><?= t('dojo.consent.projectionLabel') ?></span>
    </label>
    <label class="consent-row">
      <input type="checkbox" id="consent-retention">
      <span><?= t('dojo.consent.retentionLabel') ?></span>
    </label>
    <button id="consent-confirm" type="button" disabled><?= t('dojo.consent.confirm') ?></button>
  </div>

  <form id="composer">
    <div id="composer-row">
      <textarea id="contribution" placeholder="<?= t('dojo.composer.placeholder') ?>" disabled></textarea>
      <button id="submit" type="submit" disabled aria-label="<?= t('dojo.composer.sendAriaLabel') ?>">
        <span class="icon icon--glove" aria-hidden="true"></span>
      </button>
    </div>
    <div id="composer-footer">
      <p id="composer-disclaimer"><?= t('dojo.composer.disclaimer') ?></p>
      <div id="char-remaining"></div>
    </div>
  </form>
</main>
<div id="title-card" hidden aria-hidden="true">
  <span class="title-card__line title-card__line--slide"><?= t('dojo.titleCard.line1') ?></span>
  <span class="title-card__line title-card__line--slide"><?= t('dojo.titleCard.line2') ?></span>
  <span class="title-card__line title-card__line--grow"><?= t('dojo.titleCard.line3') ?></span>
</div>
<script>
window.CONTRIBUTION_MAX_CHARS = <?= (int) CONTRIBUTION_MAX_CHARS ?>;
window.OPENING_MESSAGE = <?= json_encode($openingMessage) ?>;
window.SE01_WAIT_BOUND_MS = <?= (int) (SE01_WAIT_BOUND_SECONDS * 1000) ?>;
window.LOCALE = <?= json_encode(resolve_locale()) ?>;
window.STRINGS = {
    dojo: {
        outcomeRateLimited: <?= json_encode(t('dojo.js.outcomeRateLimited')) ?>,
        outcomeRejected: <?= json_encode(t('dojo.js.outcomeRejected')) ?>,
        outcomeFlaggedGeneric: <?= json_encode(t('dojo.js.outcomeFlaggedGeneric')) ?>,
        outcomeFlaggedPersonalInfo: <?= json_encode(t('dojo.js.outcomeFlaggedPersonalInfo')) ?>,
        outcomeFlaggedInappropriate: <?= json_encode(t('dojo.js.outcomeFlaggedInappropriate')) ?>,
        outcomeSessionUnknown: <?= json_encode(t('dojo.js.outcomeSessionUnknown')) ?>,
        outcomeGenerationFailed: <?= json_encode(t('dojo.js.outcomeGenerationFailed')) ?>,
        confirmEndSession: <?= json_encode(t('dojo.js.confirmEndSession')) ?>,
        sessionComplete: <?= json_encode(t('dojo.js.sessionComplete')) ?>,
        installationUnavailable: <?= json_encode(t('dojo.js.installationUnavailable')) ?>,
        consentFailed: <?= json_encode(t('dojo.js.consentFailed')) ?>,
        thinking: <?= json_encode(t('dojo.js.thinking')) ?>,
        charsRemaining: <?= json_encode(t('dojo.js.charsRemaining')) ?>
    }
};
window.JUICY = {
    enabled: <?= JUICY_ENABLED ? 'true' : 'false' ?>,
    punch: <?= JUICY_PUNCH ? 'true' : 'false' ?>,
    titleCard: <?= JUICY_TITLE_CARD ? 'true' : 'false' ?>,
    wiggle: <?= JUICY_WIGGLE ? 'true' : 'false' ?>,
    sound: <?= JUICY_SOUND ? 'true' : 'false' ?>
};
</script>
<script src="<?= fasset('identity.js') ?>"></script>
<script src="<?= fasset('juicy.js') ?>"></script>
<script src="<?= fasset('zzfx.min.js') ?>"></script>
<script src="<?= fasset('sfx.js') ?>"></script>
<script src="<?= fasset('particles.js') ?>"></script>
<script src="<?= fasset('mermaid.min.js') ?>"></script>
<script src="<?= fasset('mermaid-render.js') ?>"></script>
<script src="<?= fasset('dojo.js') ?>"></script>
</body>
</html>
