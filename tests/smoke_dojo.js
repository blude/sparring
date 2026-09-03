'use strict';

/**
 * Smoke check: two pure helpers dojo.js assigns to `window` above its main
 * IIFE (public/assets/dojo.js):
 *   - SparringDojoOutcome.resolveOutcome()'s status/moderationReason ->
 *     {message, restoreText, enableComposer, wiggle, sound} decision table —
 *     the message-selection logic behind handleContributionResult's switch,
 *     minus 'ok'/'turn-limit' (those don't select from a table, see the
 *     function's own doc comment).
 *   - SparringDojoCharProgress.fillCount() — how many composer progress-bar
 *     segments are lit for a given field length / limit.
 * Doesn't exercise the rest of dojo.js — that's all DOM/fetch orchestration,
 * out of scope for a Node script, same boundary smoke_sfx.js draws.
 * Run: node tests/smoke_dojo.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

// resolveOutcome is assigned to `window` at top level, BEFORE dojo.js's main
// IIFE (which touches document/fetch/sessionStorage and would need a much
// heavier stub to eval safely) — only eval the file up to that IIFE's start.
// window.STRINGS.dojo is stubbed with distinct sentinel values (not real
// catalog prose) so this test guards status -> message *selection* without
// duplicating i18n/en.php's text into a second copy that could drift from it.
global.window = {
    STRINGS: {
        dojo: {
            outcomeRateLimited: 'SENTINEL_RATE_LIMITED',
            outcomeRejected: 'SENTINEL_REJECTED',
            outcomeFlaggedGeneric: 'SENTINEL_FLAGGED_GENERIC',
            outcomeFlaggedPersonalInfo: 'SENTINEL_FLAGGED_PERSONAL_INFO',
            outcomeFlaggedInappropriate: 'SENTINEL_FLAGGED_INAPPROPRIATE',
            outcomeSessionUnknown: 'SENTINEL_SESSION_UNKNOWN',
            outcomeGenerationFailed: 'SENTINEL_GENERATION_FAILED',
        },
    },
};
const source = fs.readFileSync(path.join(__dirname, '../public/assets/dojo.js'), 'utf8');
const iifeStart = source.indexOf('(function () {');
assert(iifeStart > 0, 'dojo.js main IIFE marker not found — did its shape change?');
eval(source.slice(0, iifeStart));
const resolveOutcome = window.SparringDojoOutcome.resolveOutcome;
const fillCount = window.SparringDojoCharProgress.fillCount;

/*
|--------------------------------------------------------------------------
| rate-limited / rejected / generation-failed / unrecognised: fixed message, same shape
|--------------------------------------------------------------------------
*/

for (const status of ['rate-limited', 'rejected', 'generation-failed', 'some-unrecognised-status']) {
    const outcome = resolveOutcome(status, undefined);
    assert.strictEqual(typeof outcome.message, 'string');
    assert.strictEqual(outcome.restoreText, true);
    assert.strictEqual(outcome.enableComposer, true);
    assert.strictEqual(outcome.wiggle, true);
    assert.strictEqual(outcome.sound, 'fumble');
}
assert.strictEqual(resolveOutcome('rate-limited', undefined).message, 'SENTINEL_RATE_LIMITED');
assert.strictEqual(resolveOutcome('rejected', undefined).message, 'SENTINEL_REJECTED');

/*
|--------------------------------------------------------------------------
| session-unknown: message only, nothing else fires
|--------------------------------------------------------------------------
*/

const sessionUnknown = resolveOutcome('session-unknown', undefined);
assert.strictEqual(sessionUnknown.message, 'SENTINEL_SESSION_UNKNOWN');
assert.strictEqual(sessionUnknown.restoreText, false);
assert.strictEqual(sessionUnknown.enableComposer, false);
assert.strictEqual(sessionUnknown.wiggle, false);
assert.strictEqual(sessionUnknown.sound, null);

/*
|--------------------------------------------------------------------------
| content-flagged: nested 3-way choice on moderationReason
|--------------------------------------------------------------------------
*/

const genericFlag = resolveOutcome('content-flagged', undefined);
assert.strictEqual(genericFlag.message, 'SENTINEL_FLAGGED_GENERIC');
const personalInfo = resolveOutcome('content-flagged', 'contains-personal-information');
assert.strictEqual(personalInfo.message, 'SENTINEL_FLAGGED_PERSONAL_INFO');
const targetsReal = resolveOutcome('content-flagged', 'targets-real-person');
assert.strictEqual(targetsReal.message, 'SENTINEL_FLAGGED_INAPPROPRIATE');
const blockedTerm = resolveOutcome('content-flagged', 'blocked-term');
assert.strictEqual(blockedTerm.message, 'SENTINEL_FLAGGED_INAPPROPRIATE');
// every content-flagged variant still restores text / re-enables / wiggles / fumbles, same as the rest
for (const outcome of [genericFlag, personalInfo, targetsReal, blockedTerm]) {
    assert.strictEqual(outcome.restoreText, true);
    assert.strictEqual(outcome.enableComposer, true);
    assert.strictEqual(outcome.wiggle, true);
    assert.strictEqual(outcome.sound, 'fumble');
}

/*
|--------------------------------------------------------------------------
| char-progress: length -> lit-segment count (ceil, clamped)
|--------------------------------------------------------------------------
*/

assert.strictEqual(fillCount(0, 600, 10), 0, 'empty -> 0 bars');
assert.strictEqual(fillCount(1, 600, 10), 1, 'first char -> bar 1 (ceil)');
assert.strictEqual(fillCount(60, 600, 10), 1, '60 chars -> bar 1');
assert.strictEqual(fillCount(61, 600, 10), 2, '61 chars -> bar 2');
assert.strictEqual(fillCount(600, 600, 10), 10, 'at limit -> all 10');
assert.strictEqual(fillCount(999, 600, 10), 10, 'over limit -> clamped to 10');

console.log('smoke_dojo: ok');
