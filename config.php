<?php
declare(strict_types=1);

/**
 * Every numeric/duration value the L3 docs marked TBC lives here, named,
 * so tuning after real pilot transcripts is a one-file edit.
 * See plan doc "Numeric defaults" table for the source TBC per constant.
 */

// --- Session / turn limits ---
const TURN_ALLOWANCE = 8;              // exchanges permitted per session (SG-04, E-01.8)
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
const DISPLAY_ITEM_LIMIT = 8;
const SCENARIO_MAX_CHARS = 140; // TF-05: trim-based scenario statement length

// --- Storage (AP-04) ---
// Outside the served docroot on the real host; project-root-relative here.
const STORE_DB_PATH = __DIR__ . '/data/store.db';
const PILOT_DATA_DIR = __DIR__ . '/data/pilot';

// --- LLM (PE-01) ---
// ponytail: no .env loader — one env var, getenv() is the whole mechanism.
const ANTHROPIC_API_KEY_ENV = 'ANTHROPIC_API_KEY';
const GENERATION_MODEL = 'claude-sonnet-5';
const CLASSIFICATION_MODEL = 'claude-haiku-4-5';
const SPARRING_PROMPT_PATH = __DIR__ . '/prompts/sparring.md';
const MODERATION_PROMPT_PATH = __DIR__ . '/prompts/moderation.md';
