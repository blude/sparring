<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('terms.title') ?></title>
<?= ogTags('/terms', t('terms.title'), t('terms.ogDescription')) ?>
<link rel="stylesheet" href="<?= fasset('content-page.css') ?>">
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
<link rel="manifest" href="/app.webmanifest">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#d32f2f">
</head>
<body>
<?= pageHeader() ?>
<h1><?= t('terms.heading') ?></h1>
<p><?= t('terms.p1') ?></p>
<p><?= t('terms.p2') ?></p>
<p><?= t('terms.p3') ?></p>
<p><?= t('terms.p4') ?></p>
<p><?= t('terms.p5') ?></p>
<p><?= t('terms.privacyLink') ?></p>
<?= pageFooter() ?>
<?= sillyBanner() ?>
</body>
</html>
