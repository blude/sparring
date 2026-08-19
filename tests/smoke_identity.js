'use strict';

/**
 * Smoke check: SparringIdentity's seed derivation (public/assets/identity.js)
 * covers the full Crockford Base32 alphabet, not just its hex-compatible
 * subset. Regression test for a bug where parseInt(id, 16) silently stopped
 * at the first non-hex char (G,H,J,K,M,N,P,Q,R,S,T,V,W,X,Y,Z), collapsing
 * >50% of session IDs onto the same alias/avatar.
 * Run: node tests/smoke_identity.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

// identity.js assigns to `window`, not `module.exports` — plain browser
// script, no build step. Stub the one global it touches and eval in place.
global.window = {};
const source = fs.readFileSync(path.join(__dirname, '../public/assets/identity.js'), 'utf8');
eval(source);
const SparringIdentity = window.SparringIdentity;

/*
|--------------------------------------------------------------------------
| deterministic: same sessionId always gives same alias/avatar
|--------------------------------------------------------------------------
*/

assert.strictEqual(SparringIdentity.alias('ABCDEFGH'), SparringIdentity.alias('ABCDEFGH'));
assert.strictEqual(SparringIdentity.avatar('ABCDEFGH'), SparringIdentity.avatar('ABCDEFGH'));

/*
|--------------------------------------------------------------------------
| a leading non-hex char must not collapse the seed to 0
|--------------------------------------------------------------------------
*/

// (this is exactly what parseInt(id, 16) did: 'G' is invalid hex, so the
// whole slice failed to parse and fell back to the `|| 0` default)
assert.notStrictEqual(SparringIdentity.alias('GGGGGGGG'), SparringIdentity.alias('00000000'));

/*
|--------------------------------------------------------------------------
| distinct IDs spread across aliases/avatars instead of piling on one bucket
|--------------------------------------------------------------------------
*/

const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
const aliases = new Set();
const avatars = new Set();
for (let i = 0; i < ALPHABET.length; i++) {
    const id = ALPHABET[i].repeat(8);
    aliases.add(SparringIdentity.alias(id));
    avatars.add(SparringIdentity.avatar(id));
}
assert.ok(aliases.size > 20, `expected wide alias spread, got ${aliases.size}/32 distinct`);
assert.ok(avatars.size > 10, `expected wide avatar spread, got ${avatars.size}/32 distinct`);

/*
|--------------------------------------------------------------------------
| Shape: "[prefix][role] [proper name...] [number]"
|--------------------------------------------------------------------------
|
| At least 3 space-separated tokens (prefix+role is one token; proper name
| may itself contain spaces, e.g. "Chan Kung-Fu", so this checks the floor,
| not an exact count).
|
*/
aliases.forEach(a => assert.ok(a.split(' ').length >= 3, `alias "${a}" missing expected token structure`));

console.log('smoke_identity: ok');
