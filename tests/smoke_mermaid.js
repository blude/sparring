'use strict';

/**
 * Smoke check: SparringMermaid.extract()'s pure fence-detection logic
 * (public/assets/mermaid-render.js). Doesn't exercise mermaid.render()
 * itself — that needs a real mermaid + DOM, out of scope for a Node script;
 * same boundary smoke_juicy.js draws around isJuicyOn's pure logic.
 * Run: node tests/smoke_mermaid.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

// mermaid-render.js assigns to `window` and calls `mermaid.initialize(...)`
// at load — stub both the same way smoke_juicy.js stubs `window` for
// juicy.js, then eval it in place, same trick a <script> tag effectively does.
global.window = {};
global.mermaid = { initialize: function () {} };
const source = fs.readFileSync(path.join(__dirname, '../public/assets/mermaid-render.js'), 'utf8');
eval(source);
const extract = window.SparringMermaid.extract;

// --- no fence at all ---
assert.strictEqual(extract('just a plain reply, no diagram here'), null);

// --- well-formed fence, text on both sides ---
let result = extract('Here\'s the shape of it:\n```mermaid\ngraph TD\nA --> B\n```\nDoes that match what you meant?');
assert.strictEqual(result.before, "Here's the shape of it:");
assert.strictEqual(result.diagram, 'graph TD\nA --> B');
assert.strictEqual(result.after, 'Does that match what you meant?');

// --- fence with nothing before or after ---
result = extract('```mermaid\ngraph TD\nA --> B\n```');
assert.strictEqual(result.before, '');
assert.strictEqual(result.diagram, 'graph TD\nA --> B');
assert.strictEqual(result.after, '');

// --- unterminated fence: falls back to null (plain text upstream) ---
assert.strictEqual(extract('```mermaid\ngraph TD\nA --> B'), null);

// --- fence for a different language is left alone ---
assert.strictEqual(extract('```js\nconsole.log(1)\n```'), null);

console.log('smoke_mermaid: ok');
