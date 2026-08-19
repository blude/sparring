<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('terms.title') ?></title>
<?= ogTags('/terms', t('terms.title'), t('terms.ogDescription')) ?>
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;touch-action:manipulation;}a{color:#d32f2f;}a:visited{color:#7b5940;}.locale-switcher{margin-bottom:1rem;font-size:0.8rem}.locale-switcher a,.locale-switcher span{margin-right:0.5rem;color:#888;text-decoration:none}.locale-switcher .locale-current{color:#222;font-weight:700}</style>
</head>
<body>
<?= localeSwitcher() ?>
<h1><?= t('terms.heading') ?></h1>
<p><?= t('terms.p1') ?></p>
<p><?= t('terms.p2') ?></p>
<p><?= t('terms.p3') ?></p>
<p><?= t('terms.p4') ?></p>
<p><?= t('terms.p5') ?></p>
<p><?= t('terms.privacyLink') ?></p>
<p><a href="/"><?= t('common.back') ?></a></p>
</body>
</html>
