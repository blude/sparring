<?php
declare(strict_types=1);
// Picker for the three sparring evaluation scenarios (ses2-ses4, see
// OPENING_PROMPTS in config.php) — links into /dojo?o=<id> the same way a
// printed QR code would. English-only, like the ses2-4 prompts themselves
// (unlike the '1'-'4' prompts, they have no German translation).
$scenarios = array_filter(
    OPENING_PROMPTS,
    fn ($id) => str_starts_with((string) $id, 'ses') && $id !== 'ses1',
    ARRAY_FILTER_USE_KEY
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Choose a scenario — Sparring</title>
<link rel="stylesheet" href="<?= fasset('content-page.css') ?>">
<?= webAppTags() ?>
<style>
.scenario-list { list-style: none; margin: 1.5rem 0; padding: 0; display: grid; gap: 0.75rem; }
.scenario-card {
    padding: 1rem 1.25rem;
    border: 1px solid #ddd;
    border-radius: 1rem;
    background: #fff;
}
.scenario-card h2 { margin: 0 0 0.375rem; font-size: 1.125rem; }
.scenario-card summary { font-size: 0.9375rem; color: #444; cursor: pointer; }
.scenario-card p { margin: 0.5rem 0 0.875rem; font-size: 0.9375rem; color: #444; }
.scenario-card .start-btn {
    display: inline-block;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: 0.5rem;
    background: #d32f2f;
    color: #fff;
    text-decoration: none;
    transition: filter 0.15s ease;
    -webkit-tap-highlight-color: transparent;
}
.scenario-card .start-btn:hover { filter: brightness(1.1); }
.scenario-card .start-btn:active { filter: brightness(0.85); }
</style>
</head>
<body>
<?= pageHeader() ?>
<h1>Choose a scenario</h1>
<ul class="scenario-list">
<?php foreach ($scenarios as $id => $prompt): ?>
<?php [$title, $blurb] = explode('. ', $prompt, 2); ?>
  <li class="scenario-card">
    <h2><?= htmlspecialchars($title, ENT_QUOTES) ?></h2>
    <details>
      <summary>Description</summary>
      <p><?= htmlspecialchars($blurb, ENT_QUOTES) ?></p>
    </details>
    <a class="start-btn" href="/dojo?o=<?= urlencode($id) ?>">Start sparring</a>
  </li>
<?php endforeach; ?>
</ul>
<?= pageFooter() ?>
<?= sillyBanner() ?>
</body>
</html>
