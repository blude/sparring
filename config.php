<?php
declare(strict_types=1);

/**
 * Every numeric/duration value the L3 docs marked TBC lives here, named,
 * so tuning after real pilot transcripts is a one-file edit.
 * See plan doc "Numeric defaults" table for the source TBC per constant.
 */

// ponytail: hand-rolled .env loader, not vlucas/phpdotenv — one file, KEY=VALUE
// lines, no quoting/interpolation support. Needed because the web SAPI (Valet's
// php-fpm, or any prod php-fpm pool) doesn't inherit a shell's `export`, unlike
// `php -S` run from a terminal. Real env vars always win — this only fills gaps.
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

// QR-11: an uncaught exception on any public endpoint (missing credential,
// unwritable store, ...) must not leak a stack trace or filesystem path.
// Every public/api/*.php requires this file first, so one handler covers all.
// Scoped to the web SAPI only — bin/*.php CLI scripts also require this file,
// and an admin running reset_db.php/backup_db.php needs the real exception
// and stack trace on stderr, not a swallowed "{"status":"error"}".
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    set_exception_handler(static function (Throwable $e): void {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error']);
    });
}

// --- Session / turn limits ---
const TURN_ALLOWANCE = 10;             // exchanges permitted per session (SG-04, E-01.8)
const CONTRIBUTION_MAX_CHARS = 600;    // (SQR-04, TF-01 FS-01-4)
const SESSION_TTL_HOURS = 6;           // undefined in docs; drives "expired" for TI-01/02/03

// --- Rate limiting (TF-03, SC-04) ---
const RATE_LIMIT_WINDOW_SECONDS = 60;
const RATE_LIMIT_MAX_REQUESTS = 10;

// --- Generation timing (SQR-01) ---
const GENERATION_TIMEOUT_SECONDS = 20; // SE-03's own bound on the provider call
const SE01_WAIT_BOUND_SECONDS = 25;    // SE-01's bound, kept above the one above

// --- Display feed (AP-02, SE-02 TF-01, TF-04) ---
const DISPLAY_POLL_INTERVAL_SECONDS = 4;
const DISPLAY_ITEM_LIMIT = 4;
const DISPLAY_COLUMNS = 2; // masonry layout, TBC-01: 2x2 target at assumed 1920x1080, more room per item
const SCENARIO_MAX_CHARS = 140; // TF-05: trim-based scenario statement length

// --- Storage (AP-04) ---
// Outside the served docroot on the real host; project-root-relative here.
const STORE_DB_PATH = __DIR__ . '/data/store.db';
const PILOT_DATA_DIR = __DIR__ . '/data/pilot';

// --- Juiciness toggles (TODO.md JUICYNESS) ---
// Global kill switch plus one per effect, each independently flippable —
// no code change needed to turn any of this off for a demo or a fault.
const JUICY_ENABLED = true;
const JUICY_PUNCH = true;
const JUICY_TITLE_CARD = true;
const JUICY_WIGGLE = true;
const JUICY_SOUND = true;
const JUICY_DISPLAY_ENTRANCE = true;

// --- LLM (PE-01) ---
const ANTHROPIC_API_KEY_ENV = 'ANTHROPIC_API_KEY';
const GENERATION_MODEL = 'claude-sonnet-5';
// Was claude-haiku-4-5. Measured 8/8 wrong (off-exercise) on a plainly on-topic
// contribution regardless of prompt wording, while Sonnet 5 was 8/8 correct on
// the same input — a capability gap, not a prompt problem. Classification calls
// are tiny (maxTokens 64), so the cost delta at exhibition volume is negligible.
const CLASSIFICATION_MODEL = 'claude-sonnet-5';
const SPARRING_PROMPT_PATH = __DIR__ . '/prompts/sparring.md';
const MODERATION_PROMPT_PATH = __DIR__ . '/prompts/moderation.md';
