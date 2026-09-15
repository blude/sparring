'use strict';

/**
 * Smoke check: window.SparringStartTaps.recordTap, the pure tap-window
 * helper behind start.php's glove easter egg (public/assets/start.js) —
 * 5 taps within 1s triggers and resets, taps spread past the window don't
 * accumulate. Doesn't exercise the rest of start.js — that's DOM/audio/
 * particle wiring, out of scope for a Node script, same boundary
 * smoke_dojo.js draws around dojo.js.
 * Run: node tests/smoke_start.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

global.window = {};
const source = fs.readFileSync(path.join(__dirname, '../public/assets/start.js'), 'utf8');
const iifeStart = source.indexOf('(function () {');
assert(iifeStart > 0, 'start.js main IIFE marker not found — did its shape change?');
eval(source.slice(0, iifeStart));
const recordTap = window.SparringStartTaps.recordTap;

/*
|--------------------------------------------------------------------------
| 5 taps within the window triggers and resets
|--------------------------------------------------------------------------
*/

let timestamps = [];
let now = 0;
for (let i = 0; i < 4; i++) {
    const result = recordTap(timestamps, now);
    assert.strictEqual(result.triggered, false, `tap ${i + 1} should not trigger`);
    timestamps = result.timestamps;
    assert.strictEqual(timestamps.length, i + 1);
    now += 100;
}
const fifth = recordTap(timestamps, now);
assert.strictEqual(fifth.triggered, true, '5th tap within window should trigger');
assert.deepStrictEqual(fifth.timestamps, [], 'triggering resets the count');

/*
|--------------------------------------------------------------------------
| a further tap after triggering starts a fresh count, not an instant re-trigger
|--------------------------------------------------------------------------
*/

const afterTrigger = recordTap(fifth.timestamps, now + 50);
assert.strictEqual(afterTrigger.triggered, false);
assert.strictEqual(afterTrigger.timestamps.length, 1);

/*
|--------------------------------------------------------------------------
| taps spread past the 1s window don't accumulate
|--------------------------------------------------------------------------
*/

timestamps = [];
now = 0;
for (let i = 0; i < 4; i++) {
    const result = recordTap(timestamps, now);
    timestamps = result.timestamps;
    now += 400; // > windowMs/threshold pace, each earlier tap ages out before the next lands
}
const stillNotTriggered = recordTap(timestamps, now);
assert.strictEqual(stillNotTriggered.triggered, false, 'taps spread past 1s should not accumulate to 5');
assert(stillNotTriggered.timestamps.length < 5);

/*
|--------------------------------------------------------------------------
| custom windowMs/threshold are honoured
|--------------------------------------------------------------------------
*/

let custom = recordTap([], 0, 500, 3);
custom = recordTap(custom.timestamps, 100, 500, 3);
custom = recordTap(custom.timestamps, 200, 500, 3);
assert.strictEqual(custom.triggered, true, 'custom threshold of 3 should trigger on the 3rd tap');

console.log('smoke_start: ok');
