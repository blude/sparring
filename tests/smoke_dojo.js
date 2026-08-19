'use strict';

/**
 * Smoke check: window.SparringDojoOutcome.resolveOutcome()'s pure
 * status/moderationReason -> {message, restoreText, enableComposer, wiggle,
 * sound} decision table (public/assets/dojo.js) — the message-selection
 * logic behind handleContributionResult's switch, minus 'ok'/'turn-limit'
 * (those don't select from a table, see the function's own doc comment).
 * Doesn't exercise the rest of dojo.js — that's all DOM/fetch orchestration,
 * out of scope for a Node script, same boundary smoke_mermaid.js draws.
 * Run: node tests/smoke_dojo.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

// resolveOutcome is assigned to `window` at top level, BEFORE dojo.js's main
// IIFE (which touches document/fetch/sessionStorage and would need a much
// heavier stub to eval safely) — only eval the file up to that IIFE's start.
global.window = {};
const source = fs.readFileSync(path.join(__dirname, '../public/assets/dojo.js'), 'utf8');
const iifeStart = source.indexOf('(function () {');
assert(iifeStart > 0, 'dojo.js main IIFE marker not found — did its shape change?');
eval(source.slice(0, iifeStart));
const resolveOutcome = window.SparringDojoOutcome.resolveOutcome;

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
assert.strictEqual(resolveOutcome('rate-limited', undefined).message, 'Too many requests — wait a moment and try again.');
assert.strictEqual(resolveOutcome('rejected', undefined).message, 'That message is empty or too long — edit it and try again.');

/*
|--------------------------------------------------------------------------
| session-unknown: message only, nothing else fires
|--------------------------------------------------------------------------
*/

const sessionUnknown = resolveOutcome('session-unknown', undefined);
assert.strictEqual(sessionUnknown.message, 'This session is no longer available — reload to start a new one.');
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
assert.strictEqual(genericFlag.message, "That message can't be shown here — edit it and try again.");
const personalInfo = resolveOutcome('content-flagged', 'contains-personal-information');
assert.strictEqual(personalInfo.message, "That message includes personal information and can't be shown here — edit it and try again.");
const targetsReal = resolveOutcome('content-flagged', 'targets-real-person');
assert.strictEqual(targetsReal.message, "That message isn't appropriate for this exhibition — edit it and try again.");
const blockedTerm = resolveOutcome('content-flagged', 'blocked-term');
assert.strictEqual(blockedTerm.message, "That message isn't appropriate for this exhibition — edit it and try again.");
// every content-flagged variant still restores text / re-enables / wiggles / fumbles, same as the rest
for (const outcome of [genericFlag, personalInfo, targetsReal, blockedTerm]) {
    assert.strictEqual(outcome.restoreText, true);
    assert.strictEqual(outcome.enableComposer, true);
    assert.strictEqual(outcome.wiggle, true);
    assert.strictEqual(outcome.sound, 'fumble');
}

console.log('smoke_dojo: ok');
