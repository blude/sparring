'use strict';

/**
 * Smoke check: SparringSfx.durationMs()/isChord() — the pure note-duration
 * and chord-detection logic behind playChain()'s scheduling
 * (public/assets/sfx.js). Doesn't exercise play()/playSequence()/unlock()/
 * playChain() itself — those call the real zzfx/zzfxX engine globals
 * (bare, not window-namespaced — see the file's own top comment), out of
 * scope for a Node script, same boundary smoke_mermaid.js draws.
 * Run: node tests/smoke_sfx.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

global.window = {};
const source = fs.readFileSync(path.join(__dirname, '../public/assets/sfx.js'), 'utf8');
eval(source);
const { durationMs, isChord } = window.SparringSfx;

// --- isChord: a chord step is an array of param arrays; a plain note step isn't ---
assert.strictEqual(isChord([[1, 2, 3]]), true); // step[0] is itself an array
assert.strictEqual(isChord([1, 2, 3]), false); // step[0] is a number

// --- durationMs: sums attack(3) + sustain(4) + release(5) + delay(16) + decay(18), in seconds -> ms ---
function closeTo(actual, expected) {
    assert(Math.abs(actual - expected) < 1e-9, `expected ${actual} to be close to ${expected}`);
}

// every slot present
closeTo(durationMs([, , , 0.1, 0.2, 0.3, , , , , , , , , , , 0.05, , 0.02]), 670); // (.1+.2+.3+.05+.02)*1000

// every duration slot omitted: falls back to zzfx's own defaults
// (attack 0, sustain 0, release .1, delay 0, decay 0) -> 100ms
closeTo(durationMs([1, 0, 226]), 100);

// only release set (the common case — PRESETS.punch's shape): attack/sustain/delay/decay default to 0
closeTo(durationMs([2.2, , 226, , 0.05, 0.19]), 240); // (.05+.19)*1000, release present, rest default

console.log('smoke_sfx: ok');
