<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('privacy.title') ?></title>
<?= ogTags('/privacy', t('privacy.title'), t('privacy.ogDescription')) ?>
<link rel="stylesheet" href="<?= fasset('content-page.css') ?>">
<?= webAppTags() ?>
</head>
<body>
<?= pageHeader() ?>
<h1><?= t('privacy.heading') ?></h1>
<p><?= t('privacy.p1') ?></p>
<p><?= t('privacy.p2') ?></p>
<p><?= t('privacy.p3') ?></p>
<p><?= t('privacy.p4') ?></p>
<p><?= t('privacy.p5') ?></p>
<h2><?= t('privacy.cookies.subheading') ?></h2>
<p><?= t('privacy.cookies.p1') ?></p>
<p><?= t('privacy.contact') ?></p>
<?= pageFooter() ?>
<?= sillyBanner() ?>
</body>
</html>
