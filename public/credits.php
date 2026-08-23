<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('credits.title') ?></title>
<?= ogTags('/credits', t('credits.title'), t('credits.intro')) ?>
<link rel="stylesheet" href="<?= fasset('content-page.css') ?>">
<?= webAppTags() ?>
</head>
<body>
<?= pageHeader() ?>
<h1><?= t('credits.heading') ?></h1>
<p><?= t('credits.intro') ?></p>
<p><?= t('credits.builtWith') ?></p>
<h2><?= t('credits.duration.heading') ?></h2>
<p><?= t('credits.duration.opening') ?></p>
<p><?= t('credits.duration.dates') ?></p>
<p><?= t('credits.duration.hours') ?></p>
<p><?= t('credits.duration.special') ?></p>
<h2><?= t('credits.author.heading') ?></h2>
<p><?= t('credits.author.bio') ?></p>
<p><?= t('credits.author.contact') ?></p>
<h2><?= t('credits.ack.heading') ?></h2>
<p><?= t('credits.ack.intro') ?></p>
<ul>
<li><a href="https://github.com/KilledByAPixel/ZzFX">ZzFX</a> by Frank Force</li>
<li><a href="https://github.com/kazuhikoarase/qrcode-generator">qrcode-generator</a> by Kazuhiko Arase</li>
<li><a href="https://github.com/hans-thiessen/Rethink-Sans/">RethinkSans</a> by Rethink</li>
<li><a href="https://pictogrammers.com/library/mdi/">Material Design Icons</a> by the Pictogrammers group</li>
<li><a href="https://easyengine.io/">EasyEngine</a></li>
<li><a href="https://www.sqlite.org/">SQLite</a></li>
<li><a href="https://figma.com/">Figma</a></li>
<li><a href="https://github.com/anthropics/claude-api">Claude API</a> and Claude Code</li>
</ul>
<?= pageFooter() ?>
<?= sillyBanner() ?>
</body>
</html>
