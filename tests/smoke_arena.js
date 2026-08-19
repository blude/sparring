'use strict';

/**
 * Smoke check: window.SparringArenaDiff.diff()'s pure add/update/remove
 * decision (public/assets/arena.js) — the diffing logic behind reconcile(),
 * pulled out so it's checkable without a DOM. Doesn't exercise the rest of
 * arena.js (poll/render/masonry placement) — that's all DOM/fetch, out of
 * scope for a Node script, same boundary smoke_mermaid.js draws.
 * Run: node tests/smoke_arena.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

// diff is assigned to `window` at top level, BEFORE arena.js's main IIFE
// (which touches document/fetch immediately at init — see buildColumns()) —
// only eval the file up to that IIFE's start.
global.window = {};
const source = fs.readFileSync(path.join(__dirname, '../public/assets/arena.js'), 'utf8');
const iifeStart = source.indexOf('(function () {');
assert(iifeStart > 0, 'arena.js main IIFE marker not found — did its shape change?');
eval(source.slice(0, iifeStart));
const diff = window.SparringArenaDiff.diff;

function item(id, overrides) {
    return Object.assign({ sessionId: id, scenario: 's', visitorContribution: 'v', sparringResponse: 'r' }, overrides || {});
}

/*
|--------------------------------------------------------------------------
| empty displayed, one incoming item: pure add
|--------------------------------------------------------------------------
*/

let result = diff(new Map(), [item('a')]);
assert.strictEqual(result.toAdd.length, 1);
assert.strictEqual(result.toUpdate.length, 0);
assert.strictEqual(result.toRemove.length, 0);
assert.strictEqual(result.next.size, 1);

/*
|--------------------------------------------------------------------------
| identical item, nothing changed: no add/update/remove
|--------------------------------------------------------------------------
*/

const displayed = new Map([['a', item('a')]]);
result = diff(displayed, [item('a')]);
assert.strictEqual(result.toAdd.length, 0);
assert.strictEqual(result.toUpdate.length, 0);
assert.strictEqual(result.toRemove.length, 0);

/*
|--------------------------------------------------------------------------
| changed field on an existing item: update, not add
|--------------------------------------------------------------------------
*/

result = diff(displayed, [item('a', { sparringResponse: 'a different response' })]);
assert.strictEqual(result.toAdd.length, 0);
assert.strictEqual(result.toUpdate.length, 1);
assert.strictEqual(result.toUpdate[0].sparringResponse, 'a different response');
assert.strictEqual(result.toRemove.length, 0);

/*
|--------------------------------------------------------------------------
| item missing from incoming: remove
|--------------------------------------------------------------------------
*/

result = diff(displayed, []);
assert.strictEqual(result.toAdd.length, 0);
assert.strictEqual(result.toUpdate.length, 0);
assert.deepStrictEqual(result.toRemove, ['a']);
assert.strictEqual(result.next.size, 0);

/*
|--------------------------------------------------------------------------
| mixed: one kept-as-is, one changed, one new, one removed
|--------------------------------------------------------------------------
*/

const before = new Map([
    ['kept', item('kept')],
    ['changed', item('changed')],
    ['gone', item('gone')],
]);
result = diff(before, [item('kept'), item('changed', { scenario: 'new scenario' }), item('new')]);
assert.strictEqual(result.toAdd.length, 1);
assert.strictEqual(result.toAdd[0].sessionId, 'new');
assert.strictEqual(result.toUpdate.length, 1);
assert.strictEqual(result.toUpdate[0].sessionId, 'changed');
assert.deepStrictEqual(result.toRemove, ['gone']);
assert.strictEqual(result.next.size, 3); // kept + changed + new — gone dropped

console.log('smoke_arena: ok');
