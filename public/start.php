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
      <img class="gloves-layer gloves-layer--honeycomb" src="assets/img/honeycomb.webp" alt="">
      <img class="gloves-layer gloves-layer--l" src="assets/img/glove-l.webp" alt="">
      <img class="gloves-layer gloves-layer--r" src="assets/img/glove-r.webp" alt="">
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
<hr class="divider">
<footer>
    <div><?= localeSwitcher(); ?></div>
    <p><?= t('start.footer.craft', ['{year}' => date('Y')]) ?></p>
    <p><?= t('start.footer.legal') ?></p>
</footer>
<?= sillyBanner() ?>
</body>
</html>
