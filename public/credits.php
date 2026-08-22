<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('credits.title') ?></title>
<?= ogTags('/credits', t('credits.title'), t('credits.intro')) ?>
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;touch-action:manipulation;background:radial-gradient(circle,rgba(0,0,0,.04) 2px,transparent 2px) 0 0/16px 16px,#fff;}a{color:#d32f2f;}a:visited{color:#7b5940;}footer{margin-top:2rem;padding:1rem 0 0;border-top:1px solid rgba(0,0,0,.1);font-size:.8125rem;color:#666}footer p{margin:0 0 1rem}footer a{color:#666}.locale-switcher{box-sizing:border-box;font-size:.75rem;display:flex;width:fit-content;flex:0 0 auto;line-height:1;gap:.25rem;border:1px solid #ddd;border-radius:999px;padding:.125rem;margin:0 auto 1rem;background:#f0f0f0}.locale-switcher a,.locale-switcher span{color:#888;text-decoration:none;padding:.5rem .75rem}.locale-switcher .locale-current{color:#222;background:#fff;border-radius:999px;font-weight:700;box-shadow:0 4px 4px rgba(0,0,0,.1)}</style>
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
<li><a href="https://github.com/mermaid-js/mermaid">mermaid.js</a></li>
<li><a href="https://github.com/hans-thiessen/Rethink-Sans/">RethinkSans</a> by Rethink</li>
<li><a href="https://pictogrammers.com/library/mdi/">Material Design Icons</a> by the Pictogrammers group</li>
<li><a href="https://easyengine.io/">EasyEngine</a></li>
<li><a href="https://www.sqlite.org/">SQLite</a></li>
<li><a href="https://figma.com/">Figma</a></li>
<li><a href="https://github.com/anthropics/claude-api">Claude API</a> and Claude Code</li>
</ul>
<?= pageFooter() ?>
</body>
</html>
