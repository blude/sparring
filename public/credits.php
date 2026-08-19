<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('credits.title') ?></title>
<?= ogTags('/credits', t('credits.title'), t('credits.intro')) ?>
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;touch-action:manipulation;}a{color:#d32f2f;}a:visited{color:#7b5940;}.locale-switcher{margin-bottom:1rem;font-size:0.8rem}.locale-switcher a,.locale-switcher span{margin-right:0.5rem;color:#888;text-decoration:none}.locale-switcher .locale-current{color:#222;font-weight:700}</style>
</head>
<body>
<?= localeSwitcher() ?>
<h1><?= t('credits.heading') ?></h1>
<p><?= t('credits.intro') ?></p>
<p><?= t('credits.builtWith') ?></p>
<h2><?= t('credits.duration.heading') ?></h2>
<p><?= t('credits.duration.opening') ?></p>
<p><?= t('credits.duration.dates') ?></p>
<p><?= t('credits.duration.hours') ?></p>
<h2><?= t('credits.author.heading') ?></h2>
<p><?= t('credits.author.bio') ?></p>
<p><?= t('credits.author.contact') ?></p>
<h2><?= t('credits.ack.heading') ?></h2>
<p><?= t('credits.ack.intro') ?></p>
<ul>
<li><a href="https://github.com/KilledByAPixel/ZzFX">ZzFX</a> by Frank Force</li>
<li><a href="https://github.com/mermaid-js/mermaid">mermaid.js</a></li>
<li><a href="https://github.com/hans-thiessen/Rethink-Sans/">RethinkSans</a> by Rethink</li>
<li><a href="https://pictogrammers.com/library/mdi/">Material Design Icons</a> by the Pictogrammers group</li>
<li><a href="https://easyengine.io/">EasyEngine</a></li>
<li><a href="https://www.sqlite.org/">SQLite</a></li>
<li><a href="https://figma.com/">Figma</a></li>
<li><a href="https://github.com/anthropics/claude-api">Claude API</a> and Claude Code</li>
</ul>
<p><a href="/"><?= t('common.back') ?></a></p>
</body>
</html>
