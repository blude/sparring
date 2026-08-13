<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Sparring — Learn Digital Design</title>
<style>
  html, body { margin: 0; }
  body {
      position: relative;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-family: system-ui, sans-serif;
      text-align: center;
      touch-action: manipulation;
      overflow-x: hidden;
      /* subtle dot-grid texture over white, matching the mockup's faint background */
      background:
          radial-gradient(circle, rgba(0,0,0,0.04) 1px, transparent 1px) 0 0/16px 16px,
          #fff;
  }
  .presented-by {
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      margin: 0;
      width: 100%;
      max-width: 402px;
      box-sizing: border-box;
      padding: 1.25rem 60px;
      font-size: 0.625rem;
      color: #666;
  }
  .content {
      width: 100%;
      max-width: 402px;
      box-sizing: border-box;
      padding: 0 60px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2rem;
  }
  .branding {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.5rem;
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
      transform: rotate(90deg);
  }
  /* logo: real "Sparring" / "スパーリング" text stays in the DOM for a11y/SEO,
     visually replaced by the exported logotype SVGs — typography is a deliberate
     design choice, not something a system font can reproduce */
  .logo {
      position: relative;
      width: 183px;
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
      width: 183px;
      height: 47px;
      background-image: url(assets/img/logo-wordmark.svg);
  }
  .subtitle {
      top: 41px;
      left: 50%;
      transform: translateX(-50%);
      width: 106px;
      height: 17px;
      background-image: url(assets/img/logo-subtitle.svg);
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
  .learn-more strong {
      color: #d32f2f;
      font-weight: 700;
      text-decoration: underline;
  }
  .divider {
      width: 140px;
      height: 0;
      border: 0;
      border-top: 1px solid rgba(0,0,0,0.1);
      margin: 2rem 0 0;
  }
  footer {
      margin: 0;
      padding: 32px;
      box-sizing: border-box;
      font-size: 0.8125rem;
      color: #444;
  }
  footer a { color: #666; }
</style>
</head>
<body>
<p class="presented-by">FH DORTMUND and SUPERRAUM presents</p>
<div class="content">
  <div class="branding">
    <div class="gloves-box">
      <img class="gloves" src="assets/img/sparring-gloves.png" alt="" width="113" height="112">
    </div>
    <div class="logo">
      <h1 class="wordmark">Sparring</h1>
      <p class="subtitle">スパーリング</p>
    </div>
  </div>
  <div class="copy">
    <p>Sparring is a versatile and engaged partner that works with you to exercise your knowledge in the emerging discipline of Digital Design.</p>
    <p>Prepare your sharpest arguments, throw in your best punches and be prepared to received some well-intentioned blows too!</p>
  </div>
  <a id="start-btn" href="input.php">Start a new session</a>
  <p class="learn-more"><strong>Learn more</strong> about Sparring&rsquo;s philosophy.</p>
</div>
<hr class="divider">
<footer>Craft with #DigitalMaterial &middot; <a href="credits.php">Credits</a></footer>
</body>
</html>
