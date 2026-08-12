<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SFX Debug — Sparring</title>
<style>
body{font:16px/1.5 system-ui,sans-serif;max-width:64rem;margin:2rem auto;padding:0 1rem;touch-action:manipulation;}
textarea{width:100%;height:4rem;font:0.9rem/1.4 ui-monospace,monospace;margin-top:0.4rem;}
button{padding:0.6rem 1rem;font:inherit;margin-top:1rem;margin-right:0.5rem;}
pre{background:#111;color:#f88;padding:0.75rem;border-radius:0.5rem;overflow-x:auto;margin-top:1rem;font-size:0.85rem;min-height:1.2rem;}
.cols{display:flex;gap:2rem;}
.cols>section{flex:1;min-width:0;}
@media (max-width:40rem){.cols{flex-direction:column;}}
</style>
</head>
<body>
<h1>SFX Debug</h1>

<p>Previews a <a href="https://killedbyapixel.github.io/ZzFX/" target="_blank" rel="noopener">ZzFX</a>
parameter array through this app's actual vendored engine
(<code>public/assets/zzfx.min.js</code>). Not linked from the app — dev
tool only. Design a sound in the official Sound Designer above, paste its
exported array below to confirm it sounds right here, then copy it into
<code>public/assets/sfx.js</code>'s <code>PRESETS</code>.</p>


<div class="cols">
<section>
<h2>Single note</h2>

<div>
  <button type="button" data-preset="punch">Load punch</button>
  <button type="button" data-preset="parry">Load parry</button>
  <button type="button" data-preset="fumble">Load fumble</button>
</div>

<label>Parameter array
  <textarea id="params" spellcheck="false" style="height:8rem"></textarea>
</label>
<details>
<summary>Parameter format</summary>
<p>Comma-separated, in the order ZzFX's own export uses. The official Sound Designer's export line is a full <code>zzfx(...)</code> call, but this tool only needs the bracketed array part (the <code>...</code> inside the parentheses).</p>
</details>

<button type="button" id="play">▶ Play</button>

<pre id="error"></pre>
</section>

<section>
<h2>Chain</h2>

<div>
  <button type="button" data-sequence="sessionEnd">Load session end</button>
  <button type="button" data-sequence="titleCard">Load title card</button>
</div>

<label>Chain of steps
  <textarea id="chain" spellcheck="false" style="height:8rem"></textarea>
</label>

<details>
<summary>Chain format</summary>
<p>Steps, one per line — a note (as before), a rest (<code>rest &lt;ms&gt;</code>),
or a chord (multiple bracketed notes on one line, separated by <code>;</code>, played simultaneously)</p>
</details>

<button type="button" id="play-chain">▶ Play chain</button>

<pre id="chain-error"></pre>


</section>
</div>

<script src="assets/juicy.js"></script>
<script src="assets/zzfx.min.js"></script>
<script src="assets/sfx.js"></script>
<script>
(function () {
    'use strict';

    // Reads sfx.js's real PRESETS/SEQUENCES — not a second copy, so this
    // can't drift out of sync with production values the way a duplicate
    // would (this bit us once already with PRESETS.punch).
    var PRESETS = window.SparringSfx.PRESETS;
    var SEQUENCES = window.SparringSfx.SEQUENCES;

    var paramsEl = document.getElementById('params');
    var errorEl = document.getElementById('error');

    function load(values) {
        paramsEl.value = values.join(', ');
        errorEl.textContent = '';
    }

    document.querySelectorAll('[data-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () { load(PRESETS[btn.dataset.preset]); });
    });

    // Accepts pasting the whole official export line verbatim, e.g.
    // "zzfx(...[2.2,,840,...]); // Random 19" — pulls just the bracketed
    // part out (falls back to the raw text if no brackets found). Can't use
    // JSON.parse: ZzFX's own export uses sparse elisions (",,") to skip
    // default-valued params, which is valid JS array literal syntax but not
    // valid JSON. Shared by the single-note and chain players below.
    function parseCall(text) {
        var bracketed = text.match(/\[([^\]]*)\]/);
        var arrayText = bracketed ? bracketed[1] : text;
        return new Function('return [' + arrayText + ']')();
    }

    document.getElementById('play').addEventListener('click', function () {
        errorEl.textContent = '';
        var values;
        try {
            values = parseCall(paramsEl.value);
        } catch (e) {
            errorEl.textContent = 'Not a valid parameter list: ' + e.message;
            return;
        }
        if (zzfxX.state === 'suspended') zzfxX.resume(); // this click is the user gesture
        zzfx.apply(null, values);
    });

    var chainEl = document.getElementById('chain');
    var chainErrorEl = document.getElementById('chain-error');

    // A SEQUENCES step is a flat note array, a plain number (rest, ms), or
    // an array of note arrays (chord — see playChain in sfx.js). Mirrors
    // parseLine below so load -> play round-trips exactly.
    function serializeStep(step) {
        if (typeof step === 'number') return 'rest ' + step;
        if (Array.isArray(step[0])) return step.map(function (n) { return '[' + n.join(', ') + ']'; }).join('; ');
        return step.join(', ');
    }

    document.querySelectorAll('[data-sequence]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            chainEl.value = SEQUENCES[btn.dataset.sequence].map(serializeStep).join('\n');
            chainErrorEl.textContent = '';
        });
    });

    // Mirrors serializeStep above: a bare "rest <ms>" line is a rest, a
    // line with more than one bracketed note is a chord (each note parsed
    // by parseCall), anything else is a single note (unchanged from before
    // — still accepts the full paste-tolerant formats parseCall handles).
    function parseLine(text) {
        var rest = text.match(/^rest\s+([\d.]+)$/i);
        if (rest) return Number(rest[1]);
        var groups = text.match(/\[[^\]]*\]/g);
        if (groups && groups.length > 1) return groups.map(parseCall);
        return parseCall(text);
    }

    document.getElementById('play-chain').addEventListener('click', function () {
        chainErrorEl.textContent = '';
        var lines = chainEl.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
        var notes;
        try {
            notes = lines.map(parseLine);
        } catch (e) {
            chainErrorEl.textContent = 'Not a valid parameter list: ' + e.message;
            return;
        }
        if (zzfxX.state === 'suspended') zzfxX.resume(); // this click is the user gesture
        window.SparringSfx.playChain(notes); // shared with production — see sfx.js
    });

    load(PRESETS.punch);
})();
</script>
</body>
</html>
