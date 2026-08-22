<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('terms.title') ?></title>
<?= ogTags('/terms', t('terms.title'), t('terms.ogDescription')) ?>
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;touch-action:manipulation;background:radial-gradient(circle,rgba(0,0,0,.04) 2px,transparent 2px) 0 0/16px 16px,#fff;}a{color:#d32f2f;}a:visited{color:#7b5940;}footer{margin-top:2rem;padding:1rem 0 0;border-top:1px solid rgba(0,0,0,.1);font-size:.8125rem;color:#666}footer p{margin:0 0 1rem}footer a{color:#666}.locale-switcher{box-sizing:border-box;font-size:.75rem;display:flex;width:fit-content;flex:0 0 auto;line-height:1;gap:.25rem;border:1px solid #ddd;border-radius:999px;padding:.125rem;margin:0 auto 1rem;background:#f0f0f0}.locale-switcher a,.locale-switcher span{color:#888;text-decoration:none;padding:.5rem .75rem}.locale-switcher .locale-current{color:#222;background:#fff;border-radius:999px;font-weight:700;box-shadow:0 4px 4px rgba(0,0,0,.1)}</style>
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
</body>
</html>
