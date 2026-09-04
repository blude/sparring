'use strict';

/**
 * Smoke check: window.SparringArenaDiff.diff()'s pure add/update/remove
 * decision and window.SparringArenaMasonry.pick()'s column choice
 * (public/assets/arena.js) — the DOM-free logic behind reconcile() and
 * pickColumn(), pulled out so it's checkable without a browser. Doesn't
 * exercise the rest of arena.js (poll/render) — that's all DOM/fetch, out
 * of scope for a Node script, same boundary smoke_sfx.js draws.
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
const pick = window.SparringArenaMasonry.pick;

function item(id, overrides) {
    return Object.assign({ sessionId: id, scenario: 's', title: null, visitorContribution: 'v', sparringResponse: 'r', exchangeId: 1, replyCount: 0 }, overrides || {});
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
| QR-reply flow: replyCount-only or exchangeId-only change still updates
|--------------------------------------------------------------------------
*/

result = diff(displayed, [item('a', { replyCount: 1 })]);
assert.strictEqual(result.toAdd.length, 0);
assert.strictEqual(result.toUpdate.length, 1);
assert.strictEqual(result.toUpdate[0].replyCount, 1);
assert.strictEqual(result.toRemove.length, 0);

result = diff(displayed, [item('a', { exchangeId: 2 })]);
assert.strictEqual(result.toAdd.length, 0);
assert.strictEqual(result.toUpdate.length, 1);
assert.strictEqual(result.toUpdate[0].exchangeId, 2);
assert.strictEqual(result.toRemove.length, 0);

/*
|--------------------------------------------------------------------------
| title arrives async (TF-07 runs after turn 1): null -> string is an update
|--------------------------------------------------------------------------
*/

result = diff(displayed, [item('a', { title: 'A refined title' })]);
assert.strictEqual(result.toAdd.length, 0);
assert.strictEqual(result.toUpdate.length, 1);
assert.strictEqual(result.toUpdate[0].title, 'A refined title');
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

/*
|--------------------------------------------------------------------------
| SparringArenaMasonry.pick(): count-balanced, shortest-height tiebreak
|--------------------------------------------------------------------------
*/

// empty columns, all same count: shortest (all 0) -> first column
assert.strictEqual(pick([0, 0], [0, 0], 6), 0);

// col 0 has one item, col 1 empty: fewer-items wins even though col 1 could be shorter anyway
assert.strictEqual(pick([1, 0], [400, 0], 6), 1);

// the regression this guards: col 0 short but already has more items -> col 1 still gets it
assert.strictEqual(pick([3, 1], [300, 900], 6), 1);

// equal counts -> shortest by height breaks the tie
assert.strictEqual(pick([2, 2], [900, 400], 6), 1);
assert.strictEqual(pick([2, 2], [400, 900], 6), 0);

// 8 items dealt one at a time into 2 columns lands 4/4, not lopsided
let cols2 = [0, 0];
let h2 = [0, 0];
for (let n = 0; n < 8; n++) {
    const c = pick(cols2, h2, 6);
    cols2[c]++;
    h2[c] += 100; // uniform item height keeps this about the count logic
}
assert.deepStrictEqual(cols2, [4, 4]);

// cap respected: a full column is skipped even when it's shortest
assert.strictEqual(pick([6, 2], [10, 900], 6), 1);

// every column at cap: fall back to column 0 rather than returning null
assert.strictEqual(pick([6, 6], [500, 400], 6), 0);

// 3 columns, uneven start: the one lone empty column wins
assert.strictEqual(pick([2, 0, 2], [800, 0, 100], 6), 1);

console.log('smoke_arena: ok');
