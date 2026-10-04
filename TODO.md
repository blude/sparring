# To Do

## High priority

## Medium priority

### Dojo (input)

- [ ] Add actions to sparring partner's response: copy, rate good, rate bad,
  regenerate
- [ ] Show timestamp in messages
- [ ] Change the visual style of the Sparring partner's message, so that the
  bubble is not visible anymore and text size is a bit larger
- [ ] Add support for the user to export their session's conversation
  (initially JSON)

### Opening scenarios

- [ ] Initial Scenario Setup: if first visitor message starts with "Sparring
  Scenario:", Sparring's immediate response should be a simple
  acknowledgment of the scenario (companion to the display-side item below)
- [ ] Never show the first exchange of a session on the display, if the
  visitor's message starts with "Sparring Scenario:" — it doesn't deliver
  friction

### Curriculum retrieval

- [ ] Narrow/restructure hub-like pages in `data/curriculum/` (e.g.
  `loesungsebene.md`, `aufbauorganisation-grundgestalt.md`) that
  cross-reference many concepts broadly — they out-rank narrowly-relevant
  pages in FTS5 search by matching more of a query's content words
  shallowly. Content-authorship, not a code fix; see
  `tests/smoke_curriculum_retrieval.php`'s documented case 2 known gap.
- [ ] Tailor the exhibition's opening prompts (`OPENING_PROMPTS` in
  `config.php`) to steer visitor phrasing toward language the curriculum
  corpus actually surfaces well — turn-1 auto-grounding is wired in now
  (`Store::searchCurriculumConcepts()`, `Sparring::processTurn`, spec's
  TF-03), so this is about improving what it finds, not enabling it.
- [ ] English contributions can coincidentally match German page titles
  carrying English loanwords (`Use`, `Case`, `System`, `Force`, `Service`,
  `Build`) — G-04 supports both languages, but `searchCurriculumConcepts()`
  isn't language-aware. Non-blocking (excerpts are never asserted as fact);
  upgrade path is detecting language before grounding, or excluding titles
  whose words double as common English ones. See
  `Store::searchCurriculumConcepts()`'s ponytail note and spec TF-03.

## Low priority / deferred

- [ ] Support message streaming — deferred. Needs backend SDK streaming
  support and a transport rewrite (SSE/chunked), materially bigger than
  CSS/JS work — separate future pass.
- [ ] Optional: frame-based animation, based on real sketches if provided
  and concept validated (format: gif or highly compressed PNGs, grungy/gruffy
  style accepted) — deferred, no sketches exist yet.

## Notes: juiciness (VERY IMPORTANT!!1)

The app elements shall incorporate juicier user interactions. Shipped so far:
animated title card on first message ("READY? SPAR!"), punch animation +
sound on submit, animated entrance for new exchanges on the display, and
wiggle/shake on rejected submissions. All switchable in `config.php` — one
global `JUICY_ENABLED` kill switch plus a `JUICY_*` constant per effect, no
code change needed to turn any off. Remaining open items are tracked above
(message streaming, frame-based animation).

**Rationale:** Adding juiciness to this exhibition piece brings fun to an
otherwise bland, "academic"-looking interface. It adds thematic flair, which
already shows up in the app's name (Sparring, the japanese characters, the
glove icon, etc). The UI should inherit videogame-inspired elements without
being over the top — a nod to 90's arcade games, martial arts.

Requirements shipped: efficient animation framework (native CSS
keyframes/transitions + Web Animations, one hand-rolled canvas particle loop
for the punch burst — no dependency added); non-blocking/performant
(`opacity`/`transform` only, compositor thread; sound is procedural, no
asset loading); target platforms iPhone 14+ / newer Pixel & Galaxy (CSS/WAAPI
+ Web Audio, broadly supported; sound tuned to ~300–900Hz for phone speaker
response, not yet ear-confirmed on actual exhibition hardware). Custom
typography, shaders, and a particle *engine* (vs. the one-off effect
shipped) remain undone and unplanned.

# Done

### Dojo (input)


- [x] Prototype / Session Evaluation — end-of-session dialog offering two
  single-choice rating questions (5-degree scale, custom labels per option)
  plus an open feedback field, always skippable. Shown on natural
  turn-limit completion and on a deliberate "End session" press. Questions
  live in `EVAL_QUESTIONS`/`EVAL_SCALE_SIZE` (config.php), answers persist
  to a new `session_evaluations` table (`Store::saveEvaluation()`), exported
  with `bin/export.php --markdown --evaluation`.
- [x] Add support for invoking a starting scenario through a query
  parameter — `/dojo?o=<n>` resolves against `OPENING_PROMPTS` in
  `config.php` (code-mapped, whitelist-validated) and auto-sends "Sparring
  Scenario: {Scenario Description}" as the first turn once consent is
  recorded.
- [x] Rename scenario query param to `o`, for opening
- [x] Show status "Sparring is thinking…" inline along with chat history,
  replaced by the incoming message
- [x] Implement playful design of starting screen (index)
- [x] Make Send button more compact (SVG arrow icon, right of input field)
- [x] Bump max sparring exchanges to 10
- [x] Increase number of unique visitor aliases possible
- [x] Debug window on input screen doesn't update after each new message
- [x] Disable zooming on double-tapping (mobile, excluding display screen)
- [x] Layout: respect Safe Area Insets (mobile, excluding display screen)
- [x] Update share image illustration

### Arena (display)

- [x] Use session title as the de facto session identifier for exchanges in
  the arena display
- [x] Display AI-generated session summaries (Haiku, possibly)
- [x] Add ability for visitors to reply to one of Sparring's replies from
  the wall projection using QR code
- [x] Add QR Code generator
- [x] Add reply counters for exchanges in the arena display
- [x] Implement playful design for displaying exchanges (display screen)
- [x] Display exchange pairs in stacked message bubbles
- [x] Display exchange pairs in a masonry layout (shipped as 2 columns, not 3)

### Both clients

- [x] Enable web app capability for both dojo (input) and arena (display)
  SEs — `dojo.webmanifest`/`arena.webmanifest` (`display: standalone`,
  arena adds `orientation: landscape`), reusing the existing 180×180
  `apple-touch-icon.png`. Arena also gets pinch-zoom/select/callout
  lockdown (viewport + CSS). No service worker/offline shell — app needs
  the live backend regardless.
- [x] Add internationalization support (German)
- [x] Update sparring logo

### Prompts and moderation

- [x] ~~Fix prompt introspection hiccup~~
- [x] Update `sparring.md` prompt (was blocked, waiting for instructions)
- [x] Update `moderation.md` prompt (was blocked, waiting for instructions)
- [x] Add prompt evaluation suite
- [x] seed `profanity_terms.txt` (English + German)

### Content pages

- [x] Add realistic content to Credits page
- [x] Add realistic content to Terms of Service page
- [x] Add realistic content to Privacy Policy page

### Export

- [x] Beef up available export options: (1) human-readable Markdown, one
  session queried by ID; (2) one full conversation (session) per line, all
  sessions; (3) one turn per line, single session queried by ID

### Spec

- [x] Fix typos in specs
- [x] Add cross-references in specs
- [x] Publish specs to HTML

### Shipped then reverted

- [x] ~~Add support for rendering Mermaid diagrams~~ (display + input +
  sparring prompt) — shipped (`3cd92c1`, prompt support added in the XML
  reorg), then removed (`72a1b44` "perf: remove mermaid diagram rendering
  support" — `mermaid.min.js` was the largest first-load asset for a
  capability that saw no real use; prompt no longer instructs Sparring to
  draw one). See `prompts/CHANGELOG.md`.
