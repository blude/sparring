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
.scenario-list {
    list-style: none;
    margin: 1.5rem 0;
    padding: 0;
    border: 1px solid #ddd;
    border-radius: 1rem;
    background: #fff;
}
.scenario-card { padding: 1rem 1.25rem; }
.scenario-card + .scenario-card { border-top: 1px solid #ddd; }
.scenario-card-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.scenario-card h2 { margin: 0; font-size: 1.125rem; min-width: 0; }
.scenario-card summary { margin-top: 0.5rem; font-size: 0.9375rem; color: #444; cursor: pointer; }
.scenario-card p { margin: 0.5rem 0 0.875rem; font-size: 0.9375rem; color: #444; }
.scenario-card .start-btn {
    display: inline-block;
    flex-shrink: 0;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: 0.5rem;
    background: rgba(211, 47, 47, 0.1);
    color: #d32f2f;
    text-decoration: none;
    transition: background 0.15s ease;
    -webkit-tap-highlight-color: transparent;
}
.scenario-card .start-btn:hover { background: rgba(211, 47, 47, 0.16); }
.scenario-card .start-btn:active { background: rgba(211, 47, 47, 0.24); }
</style>
</head>
<body>
<?= pageHeader() ?>
<h1>Choose a scenario</h1>
<ul class="scenario-list">
<?php foreach ($scenarios as $id => $prompt): ?>
<?php [$title, $blurb] = explode('. ', $prompt, 2); ?>
  <li class="scenario-card">
    <div class="scenario-card-row">
      <h2><?= htmlspecialchars($title, ENT_QUOTES) ?></h2>
      <a class="start-btn" href="/dojo?o=<?= urlencode($id) ?>">Start sparring</a>
    </div>
    <details>
      <summary>Description</summary>
      <p><?= htmlspecialchars($blurb, ENT_QUOTES) ?></p>
    </details>
  </li>
<?php endforeach; ?>
</ul>
<?= pageFooter() ?>
<?= sillyBanner() ?>
</body>
</html>
