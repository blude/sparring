<?php
declare(strict_types=1);

/**
 * Every numeric/duration value the L3 spec marked TBC lives here, named,
 * so tuning after real pilot transcripts is a one-file edit.
 * See plan doc "Numeric defaults" table for the source TBC per constant.
 */

/*
|--------------------------------------------------------------------------
| Environment
|--------------------------------------------------------------------------
|
| ponytail: hand-rolled .env loader, not vlucas/phpdotenv — one file,
| KEY=VALUE lines, no quoting/interpolation support. Needed because the
| web SAPI (Valet's php-fpm, or any prod php-fpm pool) doesn't inherit a
| shell's `export`, unlike `php -S` run from a terminal. Real env vars
| always win — this only fills gaps.
|
*/

$dotenvPath = __DIR__ . '/.env';
if (is_file($dotenvPath)) {
    foreach (file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (getenv($key) === false) { // real env takes precedence over .env
            putenv($key . '=' . trim($value));
        }
    }
}
unset($dotenvPath, $line, $key, $value);

/*
|--------------------------------------------------------------------------
| Error handling
|--------------------------------------------------------------------------
|
| QR-11: an uncaught exception on any public endpoint (missing credential,
| unwritable store, ...) must not leak a stack trace or filesystem path.
| Every public/api/*.php requires this file first, so one handler covers
| all. Scoped to the web SAPI only — bin/*.php CLI scripts also require
| this file, and an admin running reset_db.php/backup_db.php needs the
| real exception and stack trace on stderr, not a swallowed
| "{"status":"error"}".
|
*/

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    set_exception_handler(static function (Throwable $e): void {
        // API endpoints are fetched by JS, not viewed — keep the JSON reply.
        // Everything else is a page a visitor is looking at, so it gets the
        // same HTML fallback as a 404.
        if (str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error']);
            return;
        }
        renderErrorPage(500, t('error.500'));
    });
}

/*
|--------------------------------------------------------------------------
| Session / turn limits
|--------------------------------------------------------------------------
*/

const TURN_ALLOWANCE = 10;             // exchanges permitted per session (SG-04, E-01.8)
const CONTRIBUTION_MAX_CHARS = 600;    // (SQR-04, TF-01 FS-01-4)
const SESSION_TTL_HOURS = 6;           // undefined in spec; drives "expired" for TI-01/02/03

/*
|--------------------------------------------------------------------------
| Rate limiting (TF-03, SC-04)
|--------------------------------------------------------------------------
*/

const RATE_LIMIT_WINDOW_SECONDS = 60;
const RATE_LIMIT_MAX_REQUESTS = 10;

/*
|--------------------------------------------------------------------------
| Generation timing (SQR-01)
|--------------------------------------------------------------------------
*/

const GENERATION_TIMEOUT_SECONDS = 20; // SE-03's own bound on the provider call
const SE01_WAIT_BOUND_SECONDS = 25;    // SE-01's bound, kept above the one above

/*
|--------------------------------------------------------------------------
| Display feed (AP-02, SE-02 TF-01, TF-04)
|--------------------------------------------------------------------------
*/

const DISPLAY_POLL_INTERVAL_SECONDS = 4;
const DISPLAY_ITEM_LIMIT = 4;
const DISPLAY_COLUMNS = 2; // masonry layout, TBC-01: 2x2 target at assumed 1920x1080, more room per item
const SCENARIO_MAX_CHARS = 140; // TF-05: trim-based scenario statement length
const TITLE_MAX_CHARS = 38;     // header title generated from the first contribution, LLM or trim-fallback

/*
|--------------------------------------------------------------------------
| Storage (AP-04)
|--------------------------------------------------------------------------
|
| Outside the served docroot on the real host; project-root-relative here.
|
*/

const STORE_DB_PATH = __DIR__ . '/data/store.db';
const PILOT_DATA_DIR = __DIR__ . '/data/pilot';

/*
|--------------------------------------------------------------------------
| Opening prompts (QR-code-driven session starters, /dojo?scenario=<id>)
|--------------------------------------------------------------------------
|
| ID -> raw description text. Distinct from Store::setScenario()/
| derive_scenario_statement()'s "scenario" (the wall's derived heading
| from the visitor's own first contribution) — this is a pre-authored
| opener picked by which QR code was scanned. Do not rename to anything
| containing "scenario" in code-facing identifiers; the query param
| itself stays `scenario` (external/QR-facing, chosen by whoever prints
| the QR codes).
|
*/

const OPENING_PROMPTS = [
    'wicked-problems' => 'Some decisions can never fully be "solved" — only managed.',
    // add one entry per QR code before the exhibition
];

// Resolves an untrusted query-param value against the whitelist above.
// Anything not an exact key match — null, '', typo, tampered value —
// resolves to null. Array-key lookup against a fixed literal map has no
// injection surface; that IS the validation at this trust boundary.
function resolve_opening_message(?string $id): ?string
{
    if ($id === null || $id === '' || !isset(OPENING_PROMPTS[$id])) {
        return null;
    }
    return 'Sparring Scenario: ' . OPENING_PROMPTS[$id];
}

/*
|--------------------------------------------------------------------------
| Locale (i18n)
|--------------------------------------------------------------------------
|
| Two supported UI locales: 'en' (default) and 'de'. The AI's own reply
| language is a separate concern, handled entirely in prompts/sparring.md
| and prompts/title.md — it follows what the visitor typed, not this
| resolved UI locale, so no code here touches that.
|
*/

const SUPPORTED_LOCALES = ['en', 'de'];
const LOCALE_COOKIE_TTL_SECONDS = 60 * 60 * 24 * 365; // ~1 year

// Pure precedence logic, no superglobals — testable without a request
// context. Same whitelist idiom as resolve_opening_message(): untrusted
// input in, one of the two known-good values out, never anything else.
function resolve_locale_from(?string $queryLang, ?string $cookieLocale, string $acceptLanguageHeader): string
{
    if ($queryLang !== null && in_array($queryLang, SUPPORTED_LOCALES, true)) {
        return $queryLang;
    }
    if ($cookieLocale !== null && in_array($cookieLocale, SUPPORTED_LOCALES, true)) {
        return $cookieLocale;
    }
    // First Accept-Language tag matching a supported locale — a plain
    // prefix/case-insensitive match, not full RFC 4647 negotiation; two
    // locales don't need a negotiation library.
    foreach (explode(',', $acceptLanguageHeader) as $tag) {
        $tag = explode('-', strtolower(trim(explode(';', $tag, 2)[0])), 2)[0];
        if (in_array($tag, SUPPORTED_LOCALES, true)) {
            return $tag;
        }
    }
    return 'en';
}

// Request-scoped wrapper: reads the superglobals, persists an explicit
// ?lang= override to a cookie, and short-circuits to 'en' under CLI (smoke
// tests and bin/*.php scripts have no request to resolve and no response to
// set a cookie on). Memoized so every page/helper in the same request shares
// one resolution instead of re-parsing Accept-Language repeatedly.
function resolve_locale(): string
{
    static $locale = null;
    if ($locale !== null) {
        return $locale;
    }
    if (PHP_SAPI === 'cli') {
        return $locale = 'en';
    }

    $queryLang = is_string($_GET['lang'] ?? null) ? $_GET['lang'] : null;
    $locale = resolve_locale_from($queryLang, is_string($_COOKIE['locale'] ?? null) ? $_COOKIE['locale'] : null, $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

    if ($queryLang !== null && $queryLang === $locale) { // explicit override — persist it past this request
        setcookie('locale', $locale, time() + LOCALE_COOKIE_TTL_SECONDS, '/');
    }
    return $locale;
}

// Looks up $key in the resolved locale's catalog (i18n/{locale}.php),
// falling back to the English catalog if missing from the resolved one —
// never the raw key, since an unattended wall showing "dojo.consent.heading"
// is worse than showing English. {name}-style params substituted via
// strtr(). Catalogs are loaded once per request and cached.
function t(string $key, array $params = []): string
{
    static $catalogs = [];
    $locale = resolve_locale();

    if (!isset($catalogs[$locale])) {
        $catalogs[$locale] = require __DIR__ . "/i18n/$locale.php";
    }
    if (!isset($catalogs['en'])) {
        $catalogs['en'] = require __DIR__ . '/i18n/en.php';
    }

    $value = $catalogs[$locale][$key] ?? $catalogs['en'][$key] ?? null;
    if ($value === null) {
        // Missing from both catalogs is a broken key, not a missing
        // translation (that case is caught by tests/smoke_i18n.php's
        // catalog-parity check) — fail loud rather than show nothing.
        throw new RuntimeException("Missing i18n key '$key'");
    }

    return $params === [] ? $value : strtr($value, $params);
}

// Two-link EN/DE switcher, preserving the rest of the current query string
// (?s=<sessionId>, ?o=<opening>, ?debug=1, ...) so switching language never
// drops session/opening-message context that lives in the address (C-02
// requires session identity to live only there). Called identically from
// every page — same one-shared-helper pattern as ogTags()/fasset(), not a
// new templating mechanism.
function localeSwitcher(): string
{
    $current = resolve_locale();
    $links = [];
    foreach (SUPPORTED_LOCALES as $loc) {
        $label = strtoupper($loc);
        if ($loc === $current) {
            $links[] = "<span class=\"locale-current\" aria-current=\"true\">$label</span>";
            continue;
        }
        $query = http_build_query(array_merge($_GET, ['lang' => $loc]));
        $links[] = "<a href=\"?$query\">$label</a>";
    }
    return '<nav class="locale-switcher" aria-label="Language">' . implode(' ', $links) . '</nav>';
}

/*
|--------------------------------------------------------------------------
| Juiciness toggles (TODO.md JUICYNESS)
|--------------------------------------------------------------------------
|
| Global kill switch plus one per effect, each independently flippable —
| no code change needed to turn any of this off for a demo or a fault.
|
*/

const JUICY_ENABLED = true;
const JUICY_PUNCH = true;
const JUICY_TITLE_CARD = true;
const JUICY_WIGGLE = true;
const JUICY_SOUND = true;
const JUICY_DISPLAY_ENTRANCE = true;

/*
|--------------------------------------------------------------------------
| Static assets
|--------------------------------------------------------------------------
|
| Cache-busting: appends the file's mtime as a query string so editing a
| CSS/JS file forces browsers to fetch the new version instead of serving
| a stale cached copy on a plain reload (no build step, no manifest, no
| versioning scheme — just the filesystem's own timestamp).
|
*/

function fasset(string $file): string
{
    return "assets/$file?v=" . filemtime(__DIR__ . "/public/assets/$file");
}

/*
|--------------------------------------------------------------------------
| Error pages
|--------------------------------------------------------------------------
|
| Fallback UI for 404/500/etc — plain, no stack trace or technical detail
| (that's what the exception handler above swallows for API calls), just
| the code, a human-readable reason, and a way back in. Used by the
| router for unmatched routes and by the exception handler above for
| page requests.
|
*/

function renderErrorPage(int $code, string $message): never
{
    http_response_code($code);
    $lang = resolve_locale();
    $backToStart = t('error.backToStart');
    echo <<<HTML
<!doctype html>
<html lang="$lang">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$code — Sparring</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;text-align:center;touch-action:manipulation;}h1{font-size:3rem;margin:0;}a{color:#d32f2f;}a:visited{color:#7b5940;}</style>
</head>
<body>
<h1>$code</h1>
<p>$message</p>
<p><a href="/">$backToStart</a></p>
</body>
</html>
HTML;
    exit;
}

/*
|--------------------------------------------------------------------------
| Social sharing (Open Graph / Twitter Card)
|--------------------------------------------------------------------------
|
| One place to change the domain or share image — was duplicated across
| every public page's <head>, so an image/domain change meant editing
| six files instead of one.
|
*/

const SITE_NAME = 'Sparring';
const SITE_URL = 'https://sparringmethod.com';
const OG_IMAGE = SITE_URL . '/assets/img/share-image.png'; // 1200x630 og:image spec, gets cropped on some platforms

// site_name/type/image/twitter:card never change per page, only these three do.
// twitter:title/description/image are deliberately omitted — Twitter falls
// back to the og:* equivalents when they're absent, so no need to duplicate.
function ogTags(string $path, string $title, string $description): string
{
    $site_name = SITE_NAME; // heredoc interpolates variables, not bare constants
    $url = SITE_URL . $path;
    $image = OG_IMAGE; // heredoc interpolates variables, not bare constants
    return <<<HTML
<meta name="description" content="$description">
<meta property="og:type" content="website">
<meta property="og:site_name" content="$site_name">
<meta property="og:url" content="$url">
<meta property="og:title" content="$title">
<meta property="og:description" content="$description">
<meta property="og:image" content="$image">
<meta name="twitter:card" content="summary_large_image">
HTML;
}

/*
|--------------------------------------------------------------------------
| LLM (PE-01)
|--------------------------------------------------------------------------
|
| Provider is operator-selected via LLM_PROVIDER: 'anthropic' (default)
| or 'openai' — the latter is any OpenAI-Chat-Completions-compatible
| endpoint, so it also covers a local model served through LM Studio
| (point OPENAI_BASE_URL at it; OPENAI_API_KEY can stay unset). See
| createLlmClient().
|
*/

const LLM_PROVIDER_ENV = 'LLM_PROVIDER';

const ANTHROPIC_API_KEY_ENV = 'ANTHROPIC_API_KEY';
const GENERATION_MODEL = 'claude-sonnet-5';
// Was claude-sonnet-5. Originally swapped in because claude-haiku-4-5 was 8/8
// wrong on the old off-exercise category — but that category is gone (see
// f500d92, replaced by targets-real-person), and the failure was specific to
// it. Manual re-check post-swap: 6/6 correct against the new prompt's own
// examples plus edge cases, so back to haiku for the cost saving.
const CLASSIFICATION_MODEL = 'claude-haiku-4-5-20251001';

const OPENAI_API_KEY_ENV = 'OPENAI_API_KEY';
const OPENAI_BASE_URL_ENV = 'OPENAI_BASE_URL';
const OPENAI_BASE_URL_DEFAULT = 'https://api.openai.com/v1'; // e.g. http://localhost:1234/v1 for LM Studio
const OPENAI_GENERATION_MODEL_ENV = 'OPENAI_GENERATION_MODEL';
const OPENAI_GENERATION_MODEL_DEFAULT = 'gpt-4.1';
const OPENAI_CLASSIFICATION_MODEL_ENV = 'OPENAI_CLASSIFICATION_MODEL';
const OPENAI_CLASSIFICATION_MODEL_DEFAULT = 'gpt-4.1-mini';
// Model IDs are env-overridable (not a literal constant like GENERATION_MODEL
// above) because a locally-served model's ID is whatever the operator loaded
// in LM Studio — there's no sane hardcoded default for that case.

const SPARRING_PROMPT_PATH = __DIR__ . '/prompts/sparring.md';
const MODERATION_PROMPT_PATH = __DIR__ . '/prompts/moderation.md';
const TITLE_PROMPT_PATH = __DIR__ . '/prompts/title.md';

function createLlmClient(): LlmClientInterface
{
    $provider = getenv(LLM_PROVIDER_ENV) ?: 'anthropic';
    return match ($provider) {
        'anthropic' => new AnthropicLlmClient(),
        'openai' => new OpenAiLlmClient(),
        // Unrecognized value (typo, wrong case) fails loudly instead of
        // silently falling back to Anthropic — a typo here would otherwise
        // burn Anthropic credits with no visible sign OpenAI/local wasn't used.
        default => throw new RuntimeException("Unknown LLM_PROVIDER '$provider' — expected 'anthropic' or 'openai'"),
    };
}
