/*
 * SE-01 only (display.php never loads this — no user gesture on the wall
 * to unlock AudioContext). Procedurally synthesized 8-bit-style blips, no
 * audio files: one oscillator + a short gain envelope per sound, no asset
 * pipeline, no licensing question, stays inside C-04 with nothing to vendor.
 */
window.SparringSfx = (function () {
    'use strict';

    var ctx = null;

    // Lazily created, and (re)resumed on every call — iOS Safari suspends
    // AudioContext until a user gesture; unlock() is called from
    // submitContribution's tap handler for exactly that reason.
    function context() {
        if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    }

    // One oscillator + a linear-to-near-zero gain envelope. sweepTo (optional)
    // glides the frequency across the note for the "glitchy" arcade feel.
    // peakGain defaults to the production presets' level — sfx-debug.php
    // passes its own so volume is tunable there without a second code path.
    function beep(type, freq, durationMs, sweepTo, peakGain) {
        if (peakGain === undefined) peakGain = 0.15; // quiet by default — exhibition phone speakers vary
        var c = context();
        var osc = c.createOscillator();
        var gain = c.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, c.currentTime);
        if (sweepTo) osc.frequency.linearRampToValueAtTime(sweepTo, c.currentTime + durationMs / 1000);
        gain.gain.setValueAtTime(peakGain, c.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, c.currentTime + durationMs / 1000);
        osc.connect(gain).connect(c.destination);
        osc.start();
        osc.stop(c.currentTime + durationMs / 1000);
    }

    // Frequencies kept in the ~300-900Hz band, not the sub-200Hz range a
    // "punch" suggests — phone speakers (iPhone/Pixel/Galaxy) roll off hard
    // below ~300Hz and a lower sweep would be near-inaudible on the actual
    // exhibition hardware. The downward/upward sweep still reads as
    // impact/success/error at these frequencies.
    var PRESETS = {
        punch: function () { beep('square', 600, 90, 280); },
        success: function () { beep('triangle', 520, 120, 900); },
        error: function () { beep('sawtooth', 320, 180, 160); },
    };

    function play(name) {
        if (!window.isJuicyOn('sound')) return;
        var preset = PRESETS[name];
        if (preset) preset();
    }

    function unlock() {
        context(); // must run inside a user-gesture call stack — first call does the creating/resuming
    }

    // beep is exposed only for sfx-debug.php's live tuning — production
    // code always goes through play(name), never calls this directly.
    return { play: play, unlock: unlock, beep: beep };
})();
