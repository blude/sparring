<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SFX Debug — Sparring</title>
<style>
body{font:16px/1.5 system-ui,sans-serif;max-width:28rem;margin:2rem auto;padding:0 1rem;touch-action:manipulation;}
label{display:block;margin-top:1.1rem;font-size:0.9rem;color:#555;}
input[type=range]{width:100%;}
select{font:inherit;}
button{padding:0.6rem 1rem;font:inherit;margin-top:1rem;margin-right:0.5rem;}
pre{background:#111;color:#7f7;padding:0.75rem;border-radius:0.5rem;overflow-x:auto;margin-top:1rem;font-size:0.85rem;}
.notes{display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.4rem;}
.notes button{margin:0;padding:0.4rem 0.6rem;font-size:0.8rem;}
.notes button.active{background:#d32f2f;color:#fff;}
</style>
</head>
<body>
<h1>SFX Debug</h1>
<p>Live-tunes <code>public/assets/sfx.js</code>'s <code>beep()</code> params.
Not linked from the app — dev tool only. Play, adjust, copy the generated
call back into <code>sfx.js</code>'s <code>PRESETS</code>.</p>

<div>
  <button type="button" data-preset="punch">Load punch</button>
  <button type="button" data-preset="success">Load success</button>
  <button type="button" data-preset="error">Load error</button>
</div>

<label>Waveform
  <select id="type">
    <option value="square">square</option>
    <option value="sawtooth">sawtooth</option>
    <option value="triangle">triangle</option>
    <option value="sine">sine</option>
  </select>
</label>

<label>Start note: <span id="freq-val"></span></label>
<div class="notes" id="freq-notes"></div>

<label><input type="checkbox" id="sweep-on" checked> Sweep to: <span id="sweepTo-val"></span></label>
<div class="notes" id="sweepTo-notes"></div>

<label>Duration: <span id="duration-val"></span>ms
  <input type="range" id="duration" min="20" max="1000" step="10">
</label>

<label>Gain: <span id="gain-val"></span>
  <input type="range" id="gain" min="0" max="1" step="0.01">
</label>

<button type="button" id="play">▶ Play</button>

<pre id="code"></pre>

<script src="assets/juicy.js"></script>
<script src="assets/sfx.js"></script>
<script>
(function () {
    'use strict';

    // Mirrors sfx.js's own PRESETS — starting points to tweak from, not a
    // second source of truth for production (that's still sfx.js alone).
    var PRESETS = {
        punch: { type: 'square', freq: 600, duration: 90, sweepTo: 280, gain: 0.15 },
        success: { type: 'triangle', freq: 520, duration: 120, sweepTo: 900, gain: 0.15 },
        error: { type: 'sawtooth', freq: 320, duration: 180, sweepTo: 160, gain: 0.15 },
    };

    var typeEl = document.getElementById('type');
    var sweepOnEl = document.getElementById('sweep-on');
    var durationEl = document.getElementById('duration');
    var gainEl = document.getElementById('gain');
    var codeEl = document.getElementById('code');
    var freqNotesEl = document.getElementById('freq-notes');
    var sweepToNotesEl = document.getElementById('sweepTo-notes');

    // Diatonic notes only (no sharps), octaves 3-6 — covers the ~80-2000Hz
    // range a phone speaker actually reproduces (see sfx.js's own note on
    // the 300-900Hz band). Equal temperament, A4=440Hz.
    var NOTE_NAMES = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];
    var SEMITONES = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 };
    var NOTES = [];
    for (var octave = 3; octave <= 6; octave++) {
        NOTE_NAMES.forEach(function (name) {
            var midi = (octave + 1) * 12 + SEMITONES[name];
            var freq = 440 * Math.pow(2, (midi - 69) / 12);
            NOTES.push({ name: name + octave, freq: freq });
        });
    }

    var freqNote = NOTES[7]; // D4, ~punch's original 600Hz starting point
    var sweepToNote = NOTES[4]; // G3

    function buildNoteButtons(container, onPick) {
        NOTES.forEach(function (note) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = note.name;
            btn.addEventListener('click', function () { onPick(note); });
            container.appendChild(btn);
        });
    }

    function markActive(container, note) {
        Array.prototype.forEach.call(container.children, function (btn, i) {
            btn.classList.toggle('active', NOTES[i] === note);
        });
    }

    function refresh() {
        document.getElementById('freq-val').textContent = freqNote.name + ' (' + Math.round(freqNote.freq) + 'Hz)';
        document.getElementById('sweepTo-val').textContent = sweepToNote.name + ' (' + Math.round(sweepToNote.freq) + 'Hz)';
        document.getElementById('duration-val').textContent = durationEl.value;
        document.getElementById('gain-val').textContent = gainEl.value;
        markActive(freqNotesEl, freqNote);
        markActive(sweepToNotesEl, sweepToNote);
        var sweep = sweepOnEl.checked ? Math.round(sweepToNote.freq) : 'null';
        codeEl.textContent = "beep('" + typeEl.value + "', " + Math.round(freqNote.freq) + ", " + durationEl.value + ", " + sweep + ", " + gainEl.value + ");";
    }

    // Snaps a preset's raw Hz to its nearest diatonic note button, since
    // the notes grid is discrete and the presets were tuned by ear, not
    // to exact pitches.
    function nearestNote(freq) {
        return NOTES.reduce(function (best, note) {
            return Math.abs(note.freq - freq) < Math.abs(best.freq - freq) ? note : best;
        });
    }

    function load(values) {
        typeEl.value = values.type;
        freqNote = nearestNote(values.freq);
        sweepToNote = nearestNote(values.sweepTo);
        durationEl.value = values.duration;
        gainEl.value = values.gain;
        refresh();
    }

    buildNoteButtons(freqNotesEl, function (note) { freqNote = note; refresh(); });
    buildNoteButtons(sweepToNotesEl, function (note) { sweepToNote = note; refresh(); });

    [typeEl, sweepOnEl, durationEl, gainEl].forEach(function (el) {
        el.addEventListener('input', refresh);
    });

    document.querySelectorAll('[data-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () { load(PRESETS[btn.dataset.preset]); });
    });

    document.getElementById('play').addEventListener('click', function () {
        window.SparringSfx.beep(
            typeEl.value,
            freqNote.freq,
            Number(durationEl.value),
            sweepOnEl.checked ? sweepToNote.freq : undefined,
            Number(gainEl.value)
        );
    });

    load(PRESETS.punch);
})();
</script>
</body>
</html>
