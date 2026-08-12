/*
 * SE-01 only (display.php never loads this — no user gesture on the wall
 * to unlock AudioContext). Engine is ZzFXMicro (assets/zzfx.min.js,
 * vendored, MIT license — https://github.com/KilledByAPixel/ZzFX), loaded
 * before this file. It declares zzfx/zzfxX/zzfxV via top-level `let`, not
 * as window properties, so they're referenced bare here, not via `window.`.
 *
 * PRESETS: punch, fumble, and parry are all tuned (via sfx-debug.php + the
 * official ZzFX Sound Designer, https://killedbyapixel.github.io/ZzFX/).
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
        parry: [2.2, , 760, .02, .03, .01, 4, 2.2, , , , , , .9, 7.5, , .19, .79, .02, .2, -1166], // tuned via sfx-debug.php + official designer
        fumble: [1.8, 0, 261.6256, .01, .2, .12, 5, .5, -3, 3, , 10, , .5, , .01, .1, .5, .03], // tuned via sfx-debug.php + official designer
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
        // Bright pickup rising into a simultaneous C-major triad (a "chord"
        // step, see playChain() below) — the resolving chord is what makes
        // it read as uplifting rather than just an ascending run.
        titleCard: [
            [, 0, 391.9954, .01, .01, .1, 1, 1.5, , , , , , .1, , , , .91, .01], // pickup G4
            [, 0, 523.2511, .01, .01, .1, 1, 1.5, , , , , , .1, , , , .91, .01], // pickup C5
            [ // resolving chord: C4 + E4 + G4 together
                [, 0, 261.6256, .01, .05, .3, 1, 1.5, , , , , , .1, , , , .91, .05], // C4
                [, 0, 329.6276, .01, .05, .3, 1, 1.5, , , , , , .1, , , , .91, .05], // E4
                [, 0, 391.9954, .01, .05, .3, 1, 1.5, , , , , , .1, , , , .91, .05], // G4
            ],
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

    // A step is normally one zzfx param array. It may instead be an array
    // of param arrays — a "chord": every note in it fires in the same
    // setTimeout tick with no delay between them, so they start together
    // as independent, overlapping AudioBufferSourceNodes (zzfx.min.js
    // gives every zzfx() call its own node — that's what makes two calls
    // in the same tick sound simultaneous instead of one cutting the
    // other off). This is real polyphony, not a fast arpeggio.
    function isChord(step) {
        return Array.isArray(step[0]);
    }

    // Plays an array of steps back-to-back — each step's own duration
    // schedules the next. Exported (see below) so sfx-debug.php's chain
    // preview calls this instead of keeping its own copy of the
    // scheduling logic.
    function playChain(notes) {
        var offset = 0;
        notes.forEach(function (step) {
            if (isChord(step)) {
                setTimeout(function () {
                    step.forEach(function (values) { zzfx.apply(null, values); });
                }, offset);
                offset += Math.max.apply(null, step.map(durationMs));
            } else {
                setTimeout(function () { zzfx.apply(null, step); }, offset);
                offset += durationMs(step);
            }
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
