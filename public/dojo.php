<?php
declare(strict_types=1);
require __DIR__ . '/../src/Store.php';
$openingMessage = resolve_opening_message($_GET['o'] ?? null);
// QR-reply flow: unknown/tampered/non-displayable id resolves to null, same
// fallback idiom as resolve_opening_message() above.
$replyQuote = isset($_GET['r']) && is_numeric($_GET['r'])
    ? (new Store(STORE_DB_PATH))->getQuotableExchange((int) $_GET['r'])
    : null;
?>
<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= t('dojo.title') ?></title>
<?= ogTags('/dojo', t('dojo.title'), t('dojo.ogDescription')) ?>
<link rel="stylesheet" href="<?= fasset('dojo.css') ?>">
<?= webAppTags() ?>
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
      <button id="new-session-btn" type="button"><?= t('dojo.endSession') ?></button>
    </div>
  </header>

  <div id="history" aria-live="polite"></div>

  <?php
    /* Playbook: [hidden] by default, dojo.js fades it in only for a new/
      empty session (independent of #retention below — the two can be on
      screen at once), then fades it out again on the first sent message.
      No dismiss control by design. */
  ?>
  <div id="playbook" hidden>
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

  <?php
    /* End-of-session feedback (TODO.md "Session Evaluation"). Always
      skippable — every question and the feedback field are optional, per
      EVAL_QUESTIONS/EVAL_SCALE_SIZE in config.php. Shown by dojo.js on
      natural turn-limit completion and on a deliberate "End session" press. */
  ?>
  <div id="eval-dialog" hidden>
    <h2 id="eval-heading"><?= t('dojo.eval.heading') ?></h2>
    <?php foreach (EVAL_QUESTIONS as $q): ?>
    <fieldset class="eval-question" data-question="<?= $q ?>">
      <legend><?= t("dojo.eval.q.$q.prompt") ?></legend>
      <div class="eval-scale">
        <?php for ($i = 1; $i <= EVAL_SCALE_SIZE; $i++): ?>
        <label class="eval-scale-option">
          <input type="radio" name="eval-<?= $q ?>" value="<?= $i ?>">
          <span><?= t("dojo.eval.q.$q.label.$i") ?></span>
        </label>
        <?php endfor; ?>
      </div>
    </fieldset>
    <?php endforeach; ?>
    <label id="eval-feedback-label" for="eval-feedback"><?= t('dojo.eval.feedback.label') ?></label>
    <textarea id="eval-feedback" maxlength="<?= (int) CONTRIBUTION_MAX_CHARS ?>"></textarea>
    <div id="eval-actions">
      <button id="eval-skip" type="button"><?= t('dojo.eval.skip') ?></button>
      <button id="eval-submit" type="button"><?= t('dojo.eval.submit') ?></button>
    </div>
  </div>

  <div id="reply-quote" hidden>
    <p id="reply-quote-label"><?= t('dojo.replyQuote.label') ?></p>
    <p id="reply-quote-text"></p>
    <button id="reply-quote-cancel" type="button" aria-label="<?= t('dojo.replyQuote.cancelAriaLabel') ?>">&times;</button>
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
<?= sillyBanner() ?>
<script>
window.CONTRIBUTION_MAX_CHARS = <?= (int) CONTRIBUTION_MAX_CHARS ?>;
window.OPENING_MESSAGE = <?= json_encode($openingMessage) ?>;
window.REPLY_QUOTE = <?= json_encode($replyQuote) ?>;
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
        thinkingStatuses: <?= json_encode(array_map(fn($i) => t("dojo.js.thinking.$i"), range(0, 6))) ?>,
        charsRemaining: <?= json_encode(t('dojo.js.charsRemaining')) ?>,
        replyQuoteLabel: <?= json_encode(t('dojo.replyQuote.label')) ?>
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
<script src="<?= fasset('identity.js') ?>" defer></script>
<script src="<?= fasset('juicy.js') ?>" defer></script>
<script src="<?= fasset('vendor/zzfx.min.js') ?>" defer></script>
<script src="<?= fasset('sfx.js') ?>" defer></script>
<script src="<?= fasset('particles.js') ?>" defer></script>
<script src="<?= fasset('dojo.js') ?>" defer></script>
</body>
</html>
