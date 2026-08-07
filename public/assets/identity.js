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
        return parseInt(sessionId.slice(0, 8), 16) || 0;
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
