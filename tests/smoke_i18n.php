<?php
declare(strict_types=1);

/**
 * Self-check for config.php's locale/i18n helpers: resolve_locale_from()'s
 * whitelist precedence, resolve_locale()'s CLI safety, t()'s lookup/
 * fallback/param substitution, and catalog parity between i18n/en.php and
 * i18n/de.php (a missing translation should fail this test, not surface at
 * runtime on an unattended exhibition wall).
 * Run: php tests/smoke_i18n.php
 */

require __DIR__ . '/../config.php';

// --- resolve_locale_from(): pure precedence logic, no request context ---
assert(resolve_locale_from('de', null, '') === 'de');
assert(resolve_locale_from('fr', null, '') === 'en'); // unsupported query value falls through
assert(resolve_locale_from(null, 'de', '') === 'de');
assert(resolve_locale_from(null, 'xx', '') === 'en'); // unsupported cookie value falls through
assert(resolve_locale_from('de', 'en', '') === 'de'); // query wins over cookie
assert(resolve_locale_from(null, null, 'de-DE,de;q=0.9,en;q=0.8') === 'de');
assert(resolve_locale_from(null, null, 'fr-FR,fr;q=0.9') === 'en'); // no matching tag
assert(resolve_locale_from(null, null, '') === 'en'); // no header at all
assert(resolve_locale_from(null, 'de', 'en-US') === 'de'); // cookie wins over header
assert(resolve_locale_from(null, null, 'garbage;;;') === 'en'); // malformed header doesn't fatal

// --- resolve_locale(): CLI SAPI always resolves to 'en', never touches cookies ---
// (this script itself runs under `php`, i.e. PHP_SAPI === 'cli' — calling it
// here IS the CLI-safety check; a setcookie() call under CLI would emit a
// PHP warning, which the test runner would then report as a failure)
assert(resolve_locale() === 'en');
assert(resolve_locale() === 'en'); // memoized — second call is the same, not re-resolved

// --- t(): lookup, English fallback, and {param} substitution ---
assert(t('common.back') === 'Back'); // CLI locale is 'en', so this is also the fallback path
assert(t('arena.js.replyCount', ['{n}' => 3]) === '3 replies');

// --- catalog parity: every English key has a German counterpart, and vice versa ---
$en = require __DIR__ . '/../i18n/en.php';
$de = require __DIR__ . '/../i18n/de.php';
$missingFromDe = array_diff(array_keys($en), array_keys($de));
$missingFromEn = array_diff(array_keys($de), array_keys($en));
assert($missingFromDe === [], 'i18n/de.php is missing keys: ' . implode(', ', $missingFromDe));
assert($missingFromEn === [], 'i18n/en.php is missing keys: ' . implode(', ', $missingFromEn));

echo "smoke_i18n: ok\n";
