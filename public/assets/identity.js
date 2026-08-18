/*
 * Shared visitor identity: alias + avatar emoji, both pure functions of
 * sessionId. No storage, no server round-trip — deterministic so the same
 * session always gets the same alias/avatar, on the input header and on
 * the display wall alike.
 *
 * Alias scheme (naive, by design): Japanese base word, first syllable
 * doubled as a prefix, hyphen, cute/numeral suffix. E.g. "Neko" -> "Neneko-chibi".
 */
window.SparringIdentity = (function () {
    'use strict';

    var BASE_WORDS = ['Neko', 'Kuma', 'Tora', 'Usagi', 'Kitsune', 'Inu', 'Tanuki', 'Ryu', 'Hebi', 'Saru', 'Kero'];
    var SUFFIXES = ['lilo', 'dido', 'kiki', 'chibi', 'chen', 'chan', 'ie', 'hon', 'hachi', 'ichi', 'roku', 'san', 'nana', 'shi', 'go', 'ni', 'kyu', 'juu', 'maru', 'pyon', 'boo', 'tan', 'yan', 'poko', 'mochi'];
    var AVATARS = ['🐣', '🦊', '🐼', '🐸', '🐢', '🦉', '🐙', '🐿️', '🦔', '🐝', '🦋', '🐳', '🦕', '🐧', '🐨', '🦄', '🦆', '🐲'];

    function seed(sessionId) {
        // sessionId is Crockford Base32 (Store.php newSessionId) — not hex, so
        // parseInt(id, 16) would stop at the first non-hex char (G,H,J,K,M,N,P,
        // Q,R,S,T,V,W,X,Y,Z), collapsing >50% of sessions to the same seed.
        // Hash every char instead of assuming a hex-compatible alphabet.
        var h = 0;
        for (var i = 0; i < sessionId.length; i++) {
            h = (h * 31 + sessionId.charCodeAt(i)) >>> 0; // >>> 0 keeps it a positive 32-bit int
        }
        return h;
    }

    function alias(sessionId) {
        var s = seed(sessionId);
        var base = BASE_WORDS[s % BASE_WORDS.length];
        var suffix = SUFFIXES[Math.floor(s / BASE_WORDS.length) % SUFFIXES.length];
        return base.slice(0, 2) + base.toLowerCase() + '-' + suffix; // naive doubling: repeat first 2 letters as prefix
    }

    function avatar(sessionId) {
        var s = seed(sessionId);
        return AVATARS[Math.floor(s / 7) % AVATARS.length]; // different stride, decorrelates from alias pick
    }

    return { alias: alias, avatar: avatar };
})();
