'use strict';

/**
 * Smoke check: isJuicyOn()'s gating logic (public/assets/juicy.js) — the
 * global JUICY_ENABLED kill switch and each independent per-effect flag.
 * Run: node tests/smoke_juicy.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

// juicy.js assigns to `window`, not `module.exports` — it's a plain browser
// script, no build step. Stub the one global it touches and eval it in
// place, same trick a <script> tag effectively does.
global.window = {};
const source = fs.readFileSync(path.join(__dirname, '../public/assets/juicy.js'), 'utf8');
eval(source);
const isJuicyOn = window.isJuicyOn;

// --- default: no window.JUICY set at all -> fails open ---
window.JUICY = undefined;
assert.strictEqual(isJuicyOn('punch'), true);

// --- global switch off overrides every per-effect flag ---
window.JUICY = { enabled: false, punch: true, sound: true };
assert.strictEqual(isJuicyOn('punch'), false);
assert.strictEqual(isJuicyOn('sound'), false);

// --- global on, one effect off: only that effect is suppressed ---
window.JUICY = { enabled: true, punch: false, sound: true };
assert.strictEqual(isJuicyOn('punch'), false);
assert.strictEqual(isJuicyOn('sound'), true);

// --- an effect key absent from the object still fails open ---
window.JUICY = { enabled: true, punch: false };
assert.strictEqual(isJuicyOn('titleCard'), true);

console.log('smoke_juicy: ok');
