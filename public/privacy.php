<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('privacy.title') ?></title>
<?= ogTags('/privacy', t('privacy.title'), t('privacy.ogDescription')) ?>
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;touch-action:manipulation;}a{color:#d32f2f;}a:visited{color:#7b5940;}.locale-switcher{margin-bottom:1rem;font-size:0.8rem}.locale-switcher a,.locale-switcher span{margin-right:0.5rem;color:#888;text-decoration:none}.locale-switcher .locale-current{color:#222;font-weight:700}</style>
</head>
<body>
<?= localeSwitcher() ?>
<h1><?= t('privacy.heading') ?></h1>
<p><?= t('privacy.p1') ?></p>
<p><?= t('privacy.p2') ?></p>
<p><?= t('privacy.p3') ?></p>
<p><?= t('privacy.p4') ?></p>
<p><?= t('privacy.p5') ?></p>
<p><?= t('privacy.contact') ?></p>
<p><a href="/"><?= t('common.back') ?></a></p>
</body>
</html>
