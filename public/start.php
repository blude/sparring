<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= t('start.title') ?></title>
<?= ogTags('/', t('start.title'), t('start.tagline')) ?>
<link rel="stylesheet" href="<?= fasset('start.css') ?>">
<?= webAppTags() ?>
</head>
<body>
<p class="presented-by"><?= t('start.presentedBy') ?></p>
<div class="content">
  <div class="branding">
    <div class="gloves-box" role="img" aria-label="Boxing Gloves">
      <div class="gloves-layer gloves-layer--honeycomb"></div>
      <div class="gloves-layer gloves-layer--l"><div class="glove-bounce" data-glove="l"></div></div>
      <div class="gloves-layer gloves-layer--r"><div class="glove-bounce" data-glove="r"></div></div>
    </div>
    <div class="logo">
      <h1 class="wordmark">Sparring</h1>
      <p class="subtitle">スパーリング</p>
    </div>
    <p class="alpha-notice"><?= t('start.alphaNotice') ?></p>
  </div>
  <div class="copy">
    <p><?= t('start.tagline') ?></p>
    <p><?= t('start.copy.punchline') ?></p>
  </div>
  <a id="start-btn" href="/dojo"><?= t('start.cta') ?></a>
  <p class="learn-more"><?= t('start.learnMore') ?></p>
</div>
<?php /* Easter egg: 5 glove taps in a row pops this up. Same gate-card treatment as dojo's dialogs, minus the aria-modal ceremony none of those use either. */ ?>
<div id="egg-dialog" class="gate-card" hidden>
  <h2 id="egg-heading"><?= t('start.egg.title') ?></h2>
  <p id="egg-description"><?= t('start.egg.body') ?></p>
  <div id="egg-actions">
    <button id="egg-dismiss" type="button"><?= t('start.egg.dismiss') ?></button>
  </div>
</div>
<hr class="divider">
<footer>
  <div><?= localeSwitcher(); ?></div>
  <p><?= t('start.footer.craft') ?></p>
  <p><?= t('start.footer.legal', ['{year}' => date('Y')]) ?></p>
</footer>
<?= sillyBanner() ?>
<script>
window.JUICY = {
    enabled: <?= JUICY_ENABLED ? 'true' : 'false' ?>,
    punch: <?= JUICY_PUNCH ? 'true' : 'false' ?>,
    sound: <?= JUICY_SOUND ? 'true' : 'false' ?>
};
</script>
<script src="<?= fasset('juicy.js') ?>" defer></script>
<script src="<?= fasset('vendor/zzfx.min.js') ?>" defer></script>
<script src="<?= fasset('sfx.js') ?>" defer></script>
<script src="<?= fasset('particles.js') ?>" defer></script>
<script src="<?= fasset('start.js') ?>" defer></script>
</body>
</html>
