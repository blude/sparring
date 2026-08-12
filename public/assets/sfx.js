/*
 * SE-01 only (display.php never loads this — no user gesture on the wall
 * to unlock AudioContext). Engine is ZzFXMicro (assets/zzfx.min.js,
 * vendored, MIT license — https://github.com/KilledByAPixel/ZzFX), loaded
 * before this file. It declares zzfx/zzfxX/zzfxV via top-level `let`, not
 * as window properties, so they're referenced bare here, not via `window.`.
 *
 * PRESETS: punch is tuned (via sfx-debug.php + the official ZzFX Sound
 * Designer, https://killedbyapixel.github.io/ZzFX/). success/error are
 * still rough placeholder ports of the previous single-oscillator engine's
 * character — same workflow to retune: design/paste in sfx-debug.php,
 * copy the result back here.
 *
 * zzfx params, in order: volume, randomness, frequency, attack, sustain,
 * release, shape, shapeCurve, slide, deltaSlide, pitchJump, pitchJumpTime,
 * repeatTime, noise, modulation, bitCrush, delay, sustainVolume, decay,
 * tremolo, filter. Trailing zero-valued params are omitted below.
 */
window.SparringSfx = (function () {
    'use strict';

    var PRESETS = {
        punch: [2.2, , 226, , .05, .19, 4, 1.4, 50, , , , .03, .3, 9.1, .3, .12, .53, .09], // tuned via sfx-debug.php + official designer
        success: [1.2, .05, 500, 0, .05, .08, 1, 1, 40], // triangle, rising pitch — was 520->900Hz, still a rough placeholder
        error: [1.2, .1, 320, 0, .05, .15, 2, 1, -30], // sawtooth, falling pitch — was 320->160Hz, still a rough placeholder
    };

    // Multi-note sequences (arpeggios) — an array of zzfx parameter arrays,
    // played back-to-back via playChain(). A single zzfx() call is one
    // sound; this is how a real sequence of distinct notes gets built,
    // there's no engine-level "chain" primitive.
    var SEQUENCES = {
        sessionEnd: [
            [, 0, 329.6276, .05, .02, .2, 1, 1.5, , , , , , .1, , , , .91, .02], // Start E4
            [, 0, 349.2282, .02, .02, .2, 1, 1.5, , , , , , .1, , , , .91, .02], // F4
            [, 0, 391.9954, .02, .02, .2, 1, 1.5, , , , , , .1, , , , .91, .02], // G4
            [, 0, 261.6256, .02, .02, .5, 1, 1.5, , , , , , .1, , , , .91, .01], // End C4
        ],
    };

    // zzfx's own param defaults (from zzfx.min.js's arrow-function default
    // values) for the five params that add up to a note's total duration —
    // needed because an omitted/elided array slot means "use the default",
    // not 0.
    var DURATION_DEFAULTS = { attack: 0, sustain: 0, release: .1, delay: 0, decay: 0 };
    function durationMs(values) {
        var attack = values[3], sustain = values[4], release = values[5], delay = values[16], decay = values[18];
        if (attack === undefined) attack = DURATION_DEFAULTS.attack;
        if (sustain === undefined) sustain = DURATION_DEFAULTS.sustain;
        if (release === undefined) release = DURATION_DEFAULTS.release;
        if (delay === undefined) delay = DURATION_DEFAULTS.delay;
        if (decay === undefined) decay = DURATION_DEFAULTS.decay;
        return (attack + sustain + release + delay + decay) * 1000;
    }

    // Plays an array of zzfx parameter arrays back-to-back — each note's
    // own duration schedules the next. Exported (see below) so
    // sfx-debug.php's chain preview calls this instead of keeping its own
    // copy of the scheduling logic.
    function playChain(notes) {
        var offset = 0;
        notes.forEach(function (values) {
            setTimeout(function () { zzfx.apply(null, values); }, offset);
            offset += durationMs(values);
        });
    }

    function play(name) {
        if (!window.isJuicyOn('sound')) return;
        var params = PRESETS[name];
        if (params) zzfx.apply(null, params);
    }

    function playSequence(name) {
        if (!window.isJuicyOn('sound')) return;
        var notes = SEQUENCES[name];
        if (notes) playChain(notes);
    }

    function unlock() {
        // must run inside a user-gesture call stack — iOS Safari suspends
        // AudioContext until one; zzfxX is ZzFX's own, created at load time.
        if (zzfxX.state === 'suspended') zzfxX.resume();
    }

    // PRESETS/SEQUENCES/playChain exposed too — sfx-debug.php reads/calls
    // these directly rather than keeping a second copy that can drift out
    // of sync with this one.
    return { play: play, playSequence: playSequence, unlock: unlock, PRESETS: PRESETS, SEQUENCES: SEQUENCES, playChain: playChain };
})();
