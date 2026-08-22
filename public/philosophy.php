<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('philosophy.title') ?></title>
<?= ogTags('/philosophy', t('philosophy.title'), t('philosophy.ogDescription')) ?>
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
<h1><?= t('philosophy.heading') ?></h1>
<h2><?= t('philosophy.subheading') ?></h2>
<p><?= t('philosophy.body') ?></p>
<?= pageFooter() ?>
</body>
</html>
