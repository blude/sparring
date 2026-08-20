<!doctype html>
<html lang="<?= resolve_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= t('start.title') ?></title>
<?= ogTags('/', t('start.title'), t('start.tagline')) ?>
<link rel="icon" type="image/x-icon" href="<?= fasset('favicon.ico') ?>">
<style>
  html, body { margin: 0; }
  body {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-family: 'Helvetica Neue', 'Helvetica', system-ui, sans-serif;
      text-align: center;
      touch-action: manipulation;
      overflow-x: hidden;
      /* subtle dot-grid texture over white, matching the mockup's faint background */
      background:
          radial-gradient(circle, rgba(0,0,0,0.04) 2px, transparent 2px) 0 0/16px 16px,
          #fff;
  }
  .presented-by {
      margin: 0;
      width: 100%;
      max-width: 402px;
      box-sizing: border-box;
      padding: 1.25rem 32px;
      font-size: 0.625rem;
      color: #666;
  }
  .content {
      width: 100%;
      max-width: 402px;
      box-sizing: border-box;
      padding: 32px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2rem;
  }
  .branding {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
  }
  .gloves-box {
      width: 112px;
      height: 113px;
      display: flex;
      align-items: center;
      justify-content: center;
  }
  .gloves {
      display: block;
      width: 113px;
      height: 112px;
  }
  /* logo: real "Sparring" / "スパーリング" text stays in the DOM for a11y/SEO,
     visually replaced by the exported logotype PNG — typography is a deliberate
     design choice, not something a system font can reproduce */
  .logo {
      position: relative;
      width: 203px;
      height: 58px;
  }
  .wordmark, .subtitle {
      position: absolute;
      left: 0;
      margin: 0;
      overflow: hidden;
      white-space: nowrap;
      text-indent: -9999px;
      background-repeat: no-repeat;
      background-position: center;
      background-size: contain;
  }
  .wordmark {
      top: 0;
      left: 0;
      width: 203px;
      height: 58px;
      background-image: url(assets/img/logo-sparring-v2b.png);
  }
  .alpha-notice {
      margin: 0;
      font-size: 0.625rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #666;
  }
  .copy {
      display: flex;
      flex-direction: column;
      gap: 1rem;
  }
  .copy p {
      margin: 0;
      line-height: 1.4;
  }
  .copy p:first-child { color: #222; font-size: 1rem; }
  .copy p:last-child { color: #444; font-size: 0.875rem; }
  #start-btn {
      padding: 0.625rem 0.875rem;
      font: inherit;
      font-size: 0.875rem;
      font-weight: 600;
      border-radius: 0.5rem;
      border: none;
      background: #d32f2f;
      text-decoration: none;
      color: #fff;
  }
  .learn-more {
      margin: 0;
      font-size: 0.875rem;
      color: #222;
  }
  .learn-more a {
      color: #d32f2f;
      font-weight: 700;
      text-decoration: underline;
  }
  .divider {
      width: 140px;
      height: 0;
      border: 0;
      border-top: 1px solid rgba(0,0,0,0.1);
      margin: 0;
  }
  footer {
      margin: 0;
      padding: 32px;
      box-sizing: border-box;
      font-size: 0.8125rem;
      color: #666;
  }
  footer p {
      margin: 0 0 1rem;
  }
  footer a { color: #666; }
  .locale-switcher {
      box-sizing: border-box;
      font-size: 0.75rem;
      display: flex;
      width: fit-content;
      flex: 0 0 auto;
      line-height: 1;
      gap: 0.25rem;
      border: 1px solid #ccc;
      border-radius: 0.5rem;
      padding: 0.125rem;
      margin: 0 auto 1rem;
      background: #fff;
  }
  .locale-switcher a, .locale-switcher span { color: #888; text-decoration: none; padding: 0.5rem 0.75rem; }
  .locale-switcher .locale-current { color: #222; background: #f0f0f0; border-radius: 0.25rem; font-weight: 700; }
</style>
</head>
<body>
<p class="presented-by"><?= t('start.presentedBy') ?></p>
<div class="content">
  <div class="branding">
    <div class="gloves-box">
      <img class="gloves" src="assets/img/sparring-gloves.png" alt="Boxing Gloves" width="113" height="112">
    </div>
    <div class="logo">
      <h1 class="wordmark">Sparring</h1>
      <p class="subtitle">スパーリング</p>
    </div>
    <p class="alpha-notice"><?= t('start.alphaNotice') ?></p>
  </div>
  <div class="copy">
    <p><?= t('start.tagline') ?></p>
    <p><?= t('start.copy.punchline') ?></p>
  </div>
  <a id="start-btn" href="/dojo"><?= t('start.cta') ?></a>
  <p class="learn-more"><?= t('start.learnMore') ?></p>
</div>
<hr class="divider">
<footer>
    <?= localeSwitcher(); ?>
    <p><?= t('start.footer.craft', ['{year}' => date('Y')]) ?></p>
    <p><?= t('start.footer.legal') ?></p>
</footer>
</body>
</html>
