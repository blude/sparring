# 10. Juiciness effects on native web APIs, zero dependencies, kill switches

- **Status:** Accepted
- **Date:** 2026-08 (back-filled 2026-09-10)
- **References:** `TODO.md` "JUICYNESS"; `config.php` `JUICY_*`; `public/assets/juicy.js`, `public/assets/sfx.js`; `tests/smoke_juicy.js`, `tests/smoke_sfx.js`

## Context

The exhibition interface reads as bland and academic. The concept —
Sparring, the name, the glove icon, a nod to 90s arcade and martial arts —
wants videogame-inspired flair: an animated title card, a punch animation
and sound on submit, animated entrance for new exchanges on the wall, a
shake on rejected submissions. The obvious path is an animation library
and an audio-sprite loader.

## Decision

Build the effects on native browser APIs only: CSS keyframes and
transitions, the Web Animations API, one hand-rolled canvas particle loop
for the punch burst, and procedural Web Audio for sound (no audio assets,
tones tuned to ~300–900 Hz for phone speakers). No dependency added.

Every effect is switchable from `config.php` with no code change: one
global `JUICY_ENABLED` kill switch plus a `JUICY_*` constant per effect.

## Consequences

- Animations use `opacity`/`transform` only, so they stay on the
  compositor thread; sound is generated, so there is no asset load. This
  is the "non-blocking / performant" requirement met without a library.
- If an effect misbehaves on the real exhibition hardware, an operator
  flips a config constant — no redeploy of code.
- The particle loop is a one-off, not a reusable engine; a real particle
  engine, custom typography, and shaders are explicitly out of scope.
- Sound is tuned but not yet confirmed on the actual exhibition speakers —
  a hardware calibration item, not a code one.
