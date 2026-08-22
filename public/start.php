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
    <div class="gloves-box">
      <img class="gloves" src="assets/img/sparring-gloves.png" alt="Boxing Gloves" width="113" height="112">
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
    <?= localeSwitcher(); ?>
    <p><?= t('start.footer.craft', ['{year}' => date('Y')]) ?></p>
    <p><?= t('start.footer.legal') ?></p>
</footer>
<?= sillyBanner() ?>
</body>
</html>
