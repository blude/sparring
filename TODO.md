# To Do

- [ ] Add ability for visitors to reply to one of Sparring's replies from the wall projection using QR code.
- [ ] Add QR Code generator
- [ ] Add reply counters for exchanges in the arena display 
- [x] Add internationalization support (German)
- [x] Add prompt evaluation suite
- [ ] Fix prompt introspection hiccup
- [ ] Use session title as the de facto session identifier for exchanges in the arena display
- [x] Fix typos in specs
- [x] Add cross-references in specs
- [x] Publish specs to HTML
- [x] Rename scenario query param to o, for opening
- [x] Enable web app capability for both dojo (input) and arena (display) SEs. Arena may be alternatively presented on iPad — shipped: `dojo.webmanifest`/`arena.webmanifest` (`display: standalone`, arena adds `orientation: landscape`), reusing the existing 180×180 `apple-touch-icon.png`. Arena also gets pinch-zoom/select/callout lockdown (viewport + CSS) since an iPad adds a touchscreen a projector doesn't have. No service worker/offline shell — app needs the live backend regardless.
- [ ] Add actions to sparring partner's response: copy, rate good, rate bad, regenerate
- [ ] Show timestamp in messages
- [x] Beef up available export options to contemplate:
  1. Human readable format (Markdown) - one session queried by ID
  2. one full conversation (session) per line, all sessions
  3. one turn per line, single session queried by ID
- [x] Update sparring logo
- [ ] Update share image illustration

## JUICYNESS (VERY IMPORTANT!!1)

The app elements shall incorporate juicier user interactions, such as:

### Examples

- [x] Show an animated title card when the first message is sent (e.g. READY? SPAR!) — shipped literally ("READY? SPAR!"), input screen only. Triggers once the consent decision is recorded (not on the first submission itself) — a floating boxed overlay, "READY?" then "SPAR!" sliding through in sequence.
- [x] Show a quick punch animation everytime a new message is sent in input screen. — shipped: scale animation on the submit button + a small particle burst + a sound.
- [x] Animte incoming exchanges in display screen. — shipped: opacity + slide-in on newly-added items only, never on updates (keeps the "unchanged items don't redraw" requirement intact).
- [ ] Support message streaming — deferred. Needs backend SDK streaming support and a transport rewrite (SSE/chunked), materially bigger than the CSS/JS work above — separate future pass.
- [x] Wiggling / sliding / shaking / bumping / throbbing animations — partial: shipped wiggle/shake (input screen, on a rejected/failed submission) and slide-in (display screen entrance). No bump/throb built yet.
- [x] Sound effects: glitchy, arcade-like sound effects — shipped, input screen only (procedural Web Audio blips, no audio files). Display screen has no sound: it's an unattended wall projection with no user gesture to unlock playback.

New: everything above is switchable in `config.php` — one global `JUICY_ENABLED` kill switch, plus a `JUICY_*` constant per effect, no code change needed to turn any of it off.

### Requirements

- [ ] Optional: frame-based animation, based on a real sketches that can be provides, if concept is validated (format: gif or highly compressed PNGs. grungy, gruffy style is accepted) — deferred, no sketches exist yet.
- [x] Requirement: an efficient animation framework. — native CSS keyframes/transitions + Web Animations conventions, plus one hand-rolled canvas particle loop for the punch burst. No dependency added — nothing in scope needed one.
- [x] Requirement: non-blocking, performant. — animations restricted to `opacity`/`transform` (compositor thread only); sound is procedural, no asset loading.
- [ ] Desired: employ custom typography, particle emitters, effects, animations, transitions, shaders, etc. This is where external dependencies are warranted. — typography and shaders not done. Particle *effect* (not an engine/library) shipped for the punch burst.
- [x] Target platforms: Modern generation iPhone and Android devices: iPhone 14+, newer Pixel and Samsung Galaxy devices. — CSS/WAAPI + Web Audio, broadly supported; sound presets tuned into the ~300-900Hz band for phone speaker frequency response, not yet confirmed by ear on the actual exhibition hardware.

**Rationale:** Adding juiciness to this exhibition piece is a nice to bring fun to a otherwise bland, "academic"-looking interface. It adds thematic flair, which already shows up in the app's name (Sparring, the japanese characters, the glove icon, etc). The UI should inherit videogame-inspired elements without being over the top. A nod to 90's arcade games, martial arts.

## New Features

### Prototype / Session Evaluation (Important!)

- [ ] At the end of an session (after visitor runs out of turns) a dialog is presented with the option to rate the session.
- [ ] Questions TBC. But at the top of my head, one or two single-choice questions with 5 degree.
- [ ] Example 1: How challenging was this Sparring session? (Custom label for each option)
- [ ] Example 2: How knowledgeable would you rate Sparring regarding digital design? 
- [ ] Final question: open feedback input -- what stood out to you? 

Rationale: the exhibition offers a great sneaky opportunity to collect feedback about the Sparring prototype / exhibition piece.


### General

- [x] Add support for invoking a starting scenario through a query parameter — shipped: `/dojo?o=<n>` resolves against `OPENING_PROMPTS` in `config.php` (code-mapped, whitelist-validated) and auto-sends "Sparring Scenario: {Scenario Description}" as the first turn once consent is recorded. Named "opening message/prompt" in code to avoid colliding with the unrelated existing `scenario` concept (the wall's derived heading). No acknowledgment branch, no wall suppression — those stay separate, unshipped TODO items.
- [ ] update sparring.md prompt (blocked, waiting for instructions)
- [ ] update moderation.md prompt (blocked, waiting for instructions)

### Input

- [ ] Add support for the user to export their session's conversation (initially JSON).
- [x] Add support for rendering mermaid diagrams

## Improvements

### Input

- [ ] Change the visual style of the Sparring partner's message, so that the bubble is not visible anymore and text size is a bit larger.
- [x] Show status "Sparring is thinking…" inline along with chat history
  - Status message is then replaced by the incoming message.

### Display

#### Later

- [ ] Never show the first exchange of a session, if user message starts with "Sparring Scenario:".
  - Rationale: This first exchange doesn't deliver friction.
- [ ] Implement playful design for displaying exchanges (waiting for mockup).
- [ ] Add support for rendering Mermaid diagrams.
- [ ] Display AI generated session summaries (Using Haiku possibly)

### Sparring System Prompt

- [ ] Initial Scenario Setup
  - Description: If first visitor message starts with "Sparring Scenario:" then the immediate Sparring response is a simple acknowledgment of the scenario. 
- [x] Add support for generating Mermaid diagrams as part of a sparring move.

### Index

- [x] Implement playful design of starting screen (waiting for mockup).

### Misc

- [x] Add realistic content to Terms of Service page
- [x] Add realistic content to Privacy Policy page
- [ ] Add realistic content to Credits page (partially done)

## Bug Fixes

- [ ] Investigate some benign visitor messages being blocked by moderation
  - Maybe loosening the moderation can fix it.

# Done

[Move here completed To Dos]

- [x] seed profanity_terms.txt (English + German)
- [x] Make Send button more compact (SVG arrow icon, right of input field)
- [x] Bump max sparring exchanges to 10
- [x] Increase number of unique visitor aliases possible
- [x] Debug window on input screen doesn't update after each new message.
- [x] Disable zooming on double-tapping (mobile, excluding Display screen)
- [x] Layout: Respect Safe Area Insets (mobile, excluding Display screen)
- [x] Display exchange pairs in stacked message bubbles
- [x] Display exchange pairs in a masonry layout (shipped as 2 columns, not 3)