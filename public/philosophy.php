<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('philosophy.title') ?></title>
<?= ogTags('/philosophy', t('philosophy.title'), t('philosophy.ogDescription')) ?>
<link rel="stylesheet" href="<?= fasset('content-page.css') ?>">
<?= webAppTags() ?>
</head>
<body>
<?= pageHeader() ?>
<h1><?= t('philosophy.heading') ?></h1>
<p><img src="<?= fasset('img/philosophy-1.jpg') ?>" alt="<?= t('philosophy.imageAlt') ?>" class="content-image"></p>
<h2><?= t('philosophy.subheading') ?></h2>
<p><?= t('philosophy.body') ?></p>
<?= pageFooter() ?>
<?= sillyBanner() ?>
</body>
</html>
