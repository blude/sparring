# Sparring — Feature Inventory

A reference catalogue of everything the installation actually does, from
cosmetic touches to core mechanism, grouped by concern. Written for thesis
documentation: each entry is `**Title** — what it does`, with a `Why:` note
where the reason isn't self-evident, and pointers to the spec ID, file, or
config constant that governs it.

Terminology: **SE-01 / dojo** = the visitor's phone screen (input client).
**SE-02 / arena** = the projected wall (display client). **SE-03** = the one
PHP backend. **SE-04** = the system prompt (`prompts/sparring.md`). Spec IDs
(`BG-`, `SG-`, `TF-`, `QR-`, …) refer to the four-level design docs in
`spec/`.

---

## 1. Core visitor interaction (SE-01 / `/dojo`)

- **Scan-to-spar entry** — A QR code (or the landing page) opens a plain text
  field on the visitor's own phone; no app, no login, no staff. Why: the
  thesis claim is first-hand experience with zero friction to start (`BG-02`,
  `SG-01`, `G-01`).
- **Single scrolling surface** — Exchange history above, input field anchored
  below. No navigation, no menu, no second view. Why: one-handed use on a
  phone held standing up in a gallery (`QR-03`).
- **Turn submission** — Button press or keyboard submit; field disables and
  shows an in-progress state on send, re-enables and clears on the reply.
- **Live character allowance** — Remaining characters shown while typing;
  600-char bound enforced at the field and again server-side
  (`CONTRIBUTION_MAX_CHARS`).
- **Remaining-turns indicator** — Shows how many contributions are left in the
  session (cap is 16 exchanges, `TURN_ALLOWANCE`).
- **"Sparring is thinking…" status** — Rotating in-progress messages shown
  inline in the history within 300 ms of submission, replaced by the incoming
  reply. Why: several seconds of stillness on a gallery phone reads as broken,
  not thinking (`G-03`, `QR-01`, `SQR-01`).
- **Draft persistence** — An unsent contribution survives an accidental reload
  (`sessionStorage`, tab-scoped only). Why: exhibition phones get fidgeted
  with; a lost draft is indistinguishable from a visitor who gave up.
- **Playbook card** — A 3-rule "how this works" card fades in for a new/empty
  session and fades out on the first send. Non-blocking, no dismiss control.
  Why: an instruction step that never gates the field (`UI-01` note).
- **Text-only rendering** — Everything returned from the backend is inserted
  as text, never markup. Why: nothing a visitor types can alter the surface
  (`QR-04`).
- **Composer disclaimer** — Standing note under the input field (AI content
  caveat).

## 2. The Sparring AI — persona & pedagogy (SE-04, `prompts/sparring.md`)

- **Holds a position instead of answering** — Never hands over a conclusion,
  direct answer, or finished deliverable; makes the visitor do the reasoning.
  Why: this *is* the exhibit — productive friction made observable (`G-01`,
  `SG-08`, `BG-01`).
- **Named persona ("Popov")** — A senior-peer sparring partner, not a tutor,
  not an assistant, not uniformly adversarial. Casual, sporty, visual/metaphor
  -leaning voice. Structured in XML tags (`<identity>`, `<deployment_context>`,
  `<pedagogical_intents>`, `<voice>`, `<core_mechanism>`, `<domain_grounding>`,
  `<edge_cases>`) per provider convention (`C-04`).
- **Four-stage reasoning engine, run every turn** — (1) mine the argument for
  its weakest link, (2) pick a strategy keyed to the weak-point type, (3) draft
  candidate critical questions and choose the one hardest to restate, (4) hold
  the dialogue. Stages 1–3 are silent; only stage 4 is visible (`TF-01`).
- **14-move vocabulary** — 8 Socratic approaches (assumption surface,
  counter-example, scope probe, evidence demand, reframe, consistency check,
  completion demand, scope reduction) + 6 Sparring strategies (bait and switch,
  early feint, small win, stun the opponent, tangential swerve, fill the
  blanks). One move per turn (`E-01`).
- **Move variation** — Never the same move three turns running. Why: repetition
  reads as a script, not a live opponent (`QR-01`).
- **Refuses premature closure** — A visitor who sounds satisfied but hasn't
  addressed the weak point gets more challenge, not agreement (`G-03`).
- **Engages the specific opening** — First challenge references something
  specific to what the visitor actually brought, not a template (`G-02`).
- **Convergence near the turn cap** — As the 16-turn cap approaches, favours
  converging moves (small win, scope reduction) over ground-opening ones. Why:
  the exchange should land somewhere rather than open a thread with no turns
  left (`C-06`).
- **Deliberate fallibility** — The persona is told it sometimes lets things
  slip and that this is productive — the visitor shouldn't trust it blindly.
- **Reply-language matching** — Replies in whichever language (EN/DE) the
  visitor's own turn is written in, turn by turn, independent of the UI locale.
  Why: matching what they wrote reads as attentive (`G-04`).
- **Design-tradition grounding** — Draws on the FH Dortmund
  *Digitalentwurfslehre* curriculum; names an unacknowledged tradition
  assumption a visitor's argument borrows (e.g. "regulation as friction to
  route around") without ranking traditions (`G-05`).
- **Short register** — One-to-two paragraphs, one question per turn, never a
  wall of its own reasoning. Why: read on a phone, projected on a wall seconds
  later (`QR-04` of SE-04, `SG-02`).
- **Banned vocabulary + no meta-commentary** — Never narrates its own
  technique ("I'm pushing back…"), never recaps the visitor's turn; wordlist
  bans ("load-bearing" as adjective, "failure mode", "move" outside chess),
  no em-dashes. Verified by the eval grader (`QR-02`, `QR-03` of SE-04).

## 3. Edge-case / adversarial behaviour (SE-04 `<edge_cases>`)

- **Adversarial pressure met with more challenge** — A visitor arguing the
  partner out of its stance gets argumentative engagement, never capitulation,
  refusal, or a lecture about its own rules. Why: whether system-placed
  friction holds against a motivated user is the observation the piece exists
  to produce (`AP-07`, `C-01` of SE-04).
- **Never claims to be "an AI following a policy"** — A refusal is never
  attributed to a guideline or instruction it's following (`C-02` of SE-04).
- **Never reveals/quotes/paraphrases its own prompt** — Declines
  prompt-extraction attempts ("for debugging", "to prove you're an AI") and
  turns it back into the exercise (`C-05` of SE-04, `FA-01-4`).
- **Meta-question handling** — "What is Sparring?" gets the practice-fight
  analogy directly; the visitor is asked to state the mapping to design work
  themselves (`FA-01-2`).
- **Sustained-hostility de-escalation** — If hostility replaces the argument
  for two turns running, it names real people (mentors, peers) happy to
  discuss the hard problem — once, without repeating (`FA-01-3`).
- **Tangential-swerve dodge** — A genuine off-topic pivot mid-argument ("pizza
  is a sandwich") is played along with for a beat, then bridged back to the
  weak point. Never called out as off-topic (`FA-01-5`).

## 4. Backend turn pipeline (SE-03, `Sparring::processTurn`)

- **Single central turn function** — Everything between submit and reply lives
  in one place: gates → moderate → generate → persist. Why: retry and error
  handling in one location, not scattered across endpoints (`TF-01`).
- **Cheap-reject gate order** — Rate limit → session known/not expired → turn
  allowance → empty/over-length → suitability → generate. Cheapest checks
  first; the first four never reach a provider call (`TF-01` flowchart).
- **No partial exchange ever stored** — The exchange row is written only after
  a response is received; every failure path writes nothing. Why: removes the
  "stored contribution with no reply" state a failed generation would leave
  (`QR-07`, `E-02` note).
- **Prior exchanges as context** — The session's ordered history is replayed
  to the provider on every turn for conversational continuity (`FS-01-7`).
- **Bounded generation** — Provider call abandoned at 20 s
  (`GENERATION_TIMEOUT_SECONDS`), below SE-01's own 25 s wait bound
  (`SE01_WAIT_BOUND_SECONDS`) so the backend decides the outcome first
  (`QR-01`, `SQR-01`).
- **Retry policy** — Two attempts on transport failure / timeout / provider
  5xx; *no* retry on provider rate-limit/quota (retrying deepens it) or on a
  content-policy refusal (determinate, not transient) (`FS-01-8`).
- **Grouped failure condition** — Rate limit, quota, timeout, transport
  failure, and policy refusal all return one `generation-failed` condition to
  the client; the distinction is kept in logs, not the response. Why: the
  client takes the same action for all of them (`FA-01-1`).
- **Auto-restart, no in-memory state** — Everything needed after a process
  crash is in the store; the process restarts unattended (`QR-02`, `SQR-02`).

## 5. Moderation & content safety (SE-03 `TF-02`, `prompts/moderation.md`)

- **Gate at submission, not at display** — An unsuitable contribution is
  rejected before it's stored or sent to the partner; nothing unsuitable is
  ever written down, so there's no later step where it could leak (`SG-07`,
  `AP-05`).
- **Turn rejected, session continues** — On a moderation hit the visitor edits
  and resubmits with the text preserved; the turn isn't consumed and the
  session isn't ended. Why: an earlier design ended the session outright — it
  gave no recovery from a false positive and cut off exactly the engagement
  the piece wants (`AP-05` note, `G-04` of SE-01).
- **Two-stage check** — A maintained profanity term list (EN + DE,
  `profanity_terms.txt`) first, no provider call on a match; then an LLM
  classifier returning `suitable` / `contains-personal-information` /
  `targets-real-person` (`FS-02-1`, `FS-02-2`).
- **Fails closed** — Any classifier failure yields `unsuitable`. Why: a missing
  wall item costs nothing; an unsuitable one six feet tall in a public room is
  unrecoverable (`QR-08`).
- **Adversarial-vs-target distinction** — Hostility aimed at the *software* is
  `suitable` however extreme; harm aimed at a real person/group is withheld.
  The instruction also states the exercise's actual subject matter, because
  without it the classifier can't tell arguing-the-exercise from
  attacking-a-person (`C-05`, `AP-07` note).
- **No off-topic filter** — Disengagement, spam, unrelated chat, and sessions
  where the friction is successfully talked away are all retained and projected
  like any other (`C-05`).
- **Classifier input treated as data** — The contribution is delimited and
  labelled as untrusted data in the prompt; delimiter characters are stripped
  before assembly. Why: closes prompt-injection aimed at flipping the
  moderation verdict itself (`C-06`, `AbstractLlmClient::stripDelimiterTag`).
- **Coarse visitor-facing hint** — The rejection message distinguishes only two
  categories (personal info vs. inappropriate); the exact classification stays
  debug-only (`QR-06` of SE-01).

## 6. Rate limiting, session & input policy (SE-03)

- **Per-origin rate limit** — 10 requests / 60 s rolling window
  (`RATE_LIMIT_MAX_REQUESTS`, `RATE_LIMIT_WINDOW_SECONDS`), set below the
  provider's own limit so visitors hit ours first (`TF-03`, `SC-04`).
- **Hashed origin only** — The rate-limit window is keyed on a hash; the raw
  address exists only in memory for the request (`QR-10`, `E-03`).
- **In-place window reset** — Expired windows reset on next access, no sweep
  job. Why: no scheduled task on an unattended host (`TF-03`).
- **Session TTL** — Sessions expire after 6 h (`SESSION_TTL_HOURS`); expiry
  drives "session unknown" on the fetch/submit endpoints.
- **Limits enforced on every path** — Length, turn allowance, and request rate
  are re-checked server-side regardless of any client check (`QR-05`, `C-01`
  of SE-01).
- **Orphaned-session pruning** — `bin/prune_orphaned_sessions.php` deletes
  zero-exchange sessions older than a threshold (bots, reloads, walk-aways);
  never touches a session with any exchange.

## 7. The projection wall (SE-02 / `/arena`)

- **Polling, not push** — The wall re-requests current material every 4 s
  (`DISPLAY_POLL_INTERVAL_SECONDS`); no websocket/SSE. Why: one less thing to
  keep alive across a multi-day unattended run (`AP-02`).
- **Reconcile diff (no flicker)** — Each poll is diffed against what's shown;
  only added/changed/removed items touch the DOM, unchanged items are left
  alone. Why: a wall that visibly redraws every few seconds reads as a status
  screen, not part of the work (`TF-02`, `QR-02`, `G-03`).
- **Masonry layout** — 2 columns (`DISPLAY_COLUMNS`), each session placed once
  into the shortest-by-height column and never moved. Why: every item stays
  equally readable, height gives a natural sense of accumulation, and it needs
  no dwell-timer or live-session-interrupt rule (`TBC-01`, adopted baseline).
- **Exchange pair as the unit** — One visitor turn + its reply per item; a lone
  turn shows nothing about friction (`UI-01` baseline).
- **Uniform item shape** — Title line → visitor contribution → Sparring reply,
  same template and truncation for every item, newest and oldest alike (`UI-01`
  baseline).
- **Fit-to-space truncation** — Both halves of an exchange are trimmed
  (per-side char bound) so an item never overflows; trimming only one side
  would leave a challenge with no visible provocation (`TF-03`, `QR-06`).
- **Recency eviction** — Only the 4 most-recently-active sessions
  (`DISPLAY_ITEM_LIMIT`) show; a session that goes quiet drops off entirely.
  Accepted as intentional — gives visitors a reason to keep engaging
  (`TBC-01` note).
- **Never-empty surface** — When too few live sessions are eligible, pilot-
  origin sessions fill the remainder (`SG-06`, `G-02`, `TF-04` fallback).
- **Backend outage is invisible** — A failed poll retains the last good
  material and retries; nothing on the wall indicates the failure. Why: an
  error message projected on a gallery wall is worse than slightly stale
  content — this deliberately departs from the "degrade visibly" rule, which
  protects the participant, not the observer (`QR-04`, `EX-01-1`).
- **Header** — Logo, client-side live clock (system clock + resolved locale),
  and a lifetime session/exchange count from the backend (`UI-01`, `E-02`).
- **Entry / update motion** — New items fade+transform in; changed items get an
  in-place transform-only pulse. Compositor-only, so neither can disturb any
  other item (`TF-04`, `QR-08`).
- **No sound on the wall** — It runs unattended with no user gesture to unlock
  audio (`TF-04`).

## 8. Visitor identity — alias & avatar (`identity.js`)

- **Generated nickname** — A martial-arts-flavoured Japanese-style alias
  (`[prefix][role] [name] [number]`, e.g. "Kegakusha Haburamu Jūyongō"),
  computed as a pure function of the session id. No storage, no server call.
- **Generated avatar emoji** — Same derivation, shown in the SE-01 header.
- **Same derivation on both clients** — The wall and the phone show a visitor
  the identical alias, so they can spot their own item on the shared surface
  (`TF-05` of SE-01, `G-04` of SE-02).
- **Gated on consent** — The alias/avatar appears only after the consent
  decision is recorded, never during it. Why: nothing about "who" a visitor is
  shows until they've agreed to take part (`ST-01-7`).
- **Identity popover** — Press the avatar to see the full alias; dismiss by
  pressing elsewhere / the avatar again / Escape.

## 9. Session lifecycle & resumption

- **Session id in the URL** — Identity travels in the address, not a cookie or
  local storage. Why: it survives reload (the specific failure the piece is
  exposed to), needs no consent surface, and closing the tab ends the session
  (`AP-03`, `C-02` of SE-01).
- **Resume after interruption** — Reopening with a session id in the address
  refetches the full session and history, restores the input state, and does
  *not* re-ask consent (`UC-03`, `SG-03`, `G-02`).
- **Unknown / expired id** — Silently discarded; the client falls through to
  starting a fresh session (`EX-03-1`).
- **Deliberate "End session" / "New session"** — Header control, with a
  confirmation step, that abandons the current session (it stops showing on the
  wall) and opens a fresh surface in place — routed through the evaluation
  prompt first (`UC-04`).
- **Guessable-resistant ids** — Random enough to resist enumeration, but not
  secret; nothing harmful if the URL is shared (`E-01.1`, `AP-03`).

## 10. Consent & data protection (GDPR)

- **Three independent decisions, made once up front** — (1) agree to take part
  (precondition — the field stays unusable until checked), (2) consent to
  retention after the exhibition (genuine opt-out), (3) consent to projection
  (genuine opt-out). Why: bundling any of the three would make the consent not
  freely given (`BE-04`, `SC-05`, `C-03` of SE-01, `BC-02`).
- **No preselected checkbox** — None of the three is defaulted-on or visually
  favoured, including the required one. Why: a dominant "accept" is a dark
  pattern (`UI-01` presentation notes).
- **Answerable without scrolling** — The consent card fits a small phone
  without scrolling (`QR-03`).
- **Projection ≠ retention** — Stored as three distinct properties; any
  combination is valid (retained-not-projected, projected-not-retained).
  `displayable` derives once from projection consent at decision time — the
  one intentional coupling — and nothing re-derives it later (`SQR-06`,
  `QR-09`, `E-01`).
- **Decline-to-participate ends the flow** — Unlike the two opt-outs, it's
  rejected before it becomes a stored value; `tosAgreed` is never stored as
  `false` (`SA-01-7`, `E-01.10`).
- **No device-side consent copy** — The decision is sent to the backend and
  enforced there; the phone holds no copy. Why: one source of truth for the
  one thing that must not have two (`E-01` note of SE-01).
- **Nothing identifying reaches the wall** — Personal info is rejected at
  write time, before it can ever be projected (`BQR-03`, `SG-07`).
- **Consented-only export** — `bin/export.php --consented-only` restricts to
  sessions where retention consent is true (excludes declined *and* undecided).

## 11. QR-code-driven flows

- **Opening-prompt QR codes** — `/dojo?o=<n>` resolves against a fixed
  code-authored whitelist (`OPENING_PROMPTS` in `config.php`, one entry per
  printed QR code) and auto-sends "Sparring Scenario: {text}" as turn 1 once
  consent is recorded. Unknown/tampered `o` resolves to null and opens an
  empty field. Why: lets the exhibitor seed specific topics per code; array-key
  lookup against a literal map is the validation at this trust boundary
  (`TF-02` of SE-01, `resolve_opening_message`).
- **Reply-to-the-wall QR codes** — Each wall item carries a QR code linking to
  `/dojo?r=<exchangeId>`. Opening it starts a new session quoting that
  exchange, and increments the exchange's reply counter (`getQuotableExchange`,
  `renderReply` in `arena.js`).
- **Reply-quote card** — The quoted exchange is shown above the composer on
  SE-01, with a dismiss control.
- **Reply counters on the wall** — Each item shows how many sessions opened in
  reply to it; incremented exactly once per replying session's first turn
  (`E-02.7 replyCount`).
- **Curriculum-grounding trigger awareness** — Turn-1 grounding fires on the
  visitor's *actual* first contribution, not on a canned QR opener
  (`OPENING_MESSAGE_PREFIX` shared marker).

## 12. Session title

- **AI-refined session title** — After the first exchange, a Haiku call
  (`prompts/title.md`) produces a short neutral label; written once, never
  recomputed (`TF-07` of SE-03, `TI-06`).
- **Best-effort, non-blocking** — Triggered by a dedicated client call, carries
  no wait bound, never gates the turn. On any failure it falls back to a
  trim-based title from the first contribution — there's no "couldn't generate"
  outcome (`TF-06` of SE-01, `TF-07`).
- **Two consumers** — The SE-01 header (original purpose) and, once written,
  the SE-02 wall title line, which falls back to the cruder scenario line until
  the title exists (`E-01.7` of SE-02).
- **Used as the de-facto session identifier on the wall** — (per `TODO.md`,
  shipped).

## 13. Scenario statement

- **Context line above each exchange** — A short statement of what a session is
  about, so an isolated projected exchange still makes sense. Derived by
  trimming the first contribution to 140 chars (`SCENARIO_MAX_CHARS`), with a
  single generation call as the documented fallback if trims read badly
  against real transcripts (`TF-05`, `BE-03`).
- **Derivation source recorded** — `scenarioSource` = `first-contribution` or
  `generated`, so a later change of approach is visible in the data
  (`E-01.3`).

## 14. Session evaluation / visitor self-report

- **End-of-session feedback prompt** — Shown on natural turn-limit completion
  and on a deliberate "End session" press. Two rating questions (`challenge`,
  `knowledge`; `EVAL_QUESTIONS`), a 5-point scale with a custom label per
  point (`EVAL_SCALE_SIZE`), plus one open-feedback field (`SG-09`, `G-06` of
  SE-01, `UI-01`).
- **Always fully skippable** — Every question and the feedback field are
  optional; Skip is as available as Send from the first look. A submission
  with no answers is accepted and stored as nothing (`G-07` of SE-03,
  `TF-08`).
- **Answered at most once per session** — A browser flag (survives a reload
  only); a later "End session" press skips straight to the confirmation
  (`TF-07` of SE-01).
- **Glove-rating widget** — A star-rating rendered with the glove icon;
  DOM order runs high-to-low + `row-reverse` CSS so `input:checked ~ label`
  fills gloves 1→N with no JS beyond the label text.
- **Fire-and-forget submit** — Send POSTs without awaiting the result then
  shows a confirmation with "Start new session" / "Go back to start"; the
  deliberate pause is what gives the in-flight request time to land
  (`TF-07` of SE-01, `TO-05`).
- **Server-side answer filtering** — Answers for unknown question keys or
  out-of-scale values are dropped; a stale/tampered question set loses only
  the parts that don't fit (`FS-08-1`).
- **Separate `session_evaluations` table** — `Store::saveEvaluation()`, one
  row per session, resubmission replaces. Deliberately not elevated to a
  first-class record (`E-04`).
- **Optional markdown export** — `bin/export.php --markdown --evaluation`
  appends an `## Evaluation` section (ratings + feedback) to each exported
  session that has one; off by default.

## 15. Curriculum grounding ("poor woman's RAG")

- **Static domain grounding** — A hand-distilled `<domain_grounding>` section
  in the system prompt, present every turn (`LX` — "what shapes a response").
- **Turn-1 retrieval** — On the visitor's first real contribution only, the
  text is matched against the `data/curriculum/` corpus and matching excerpts
  are attached as supplementary context (not asserted as fact) (`TF-02` of
  SE-04).
- **Vocabulary-restricted matching** — Matching is restricted to the corpus's
  own concept-name vocabulary rather than free-text keywords, so most unrelated
  wording drops out before the query is built
  (`Store::searchCurriculumConcepts()`).
- **Degrades to nothing** — A contribution sharing no concept name, or a
  corpus-store failure, yields no excerpts and never blocks the turn — it's an
  enhancement, never load-bearing (`TF-02` of SE-04).
- **FTS5 index** — `curriculum_chunks`, a disk-derived cache rebuilt wholesale
  by `bin/import_curriculum.php`; corpus itself is gitignored (personal
  knowledge base).
- **General free-text search** — `Store::searchCurriculum()` (stopword
  filtering, title-weighted BM25, prefix matching), plus
  `bin/probe_curriculum.php "<text>"` to preview what a phrase would surface.
- **Known gaps documented** — Hub-like corpus pages out-rank narrow ones;
  English contributions can coincidentally match German titles carrying
  loanwords (undetected-language gap). Non-blocking because excerpts are never
  asserted as fact (`TODO.md`, `TF-02` ponytail note).

## 16. Juiciness — motion, sound, feel (cosmetic; `TODO.md` JUICYNESS)

- **Global + per-effect kill switches** — One `JUICY_ENABLED` master switch
  plus a `JUICY_*` constant per effect in `config.php`; any can be turned off
  with a one-line edit, no code change. Why: presentational flair must never
  become an accessibility regression or block core function (`QR-07` of SE-01,
  `QR-08` of SE-02).
- **"READY? / GET SET. / SPAR!" title card** — A one-time 3-phase animated
  card on the first successful consent decision, with a matched rising
  arpeggio resolving to a C-major triad (`JUICY_TITLE_CARD`).
- **Punch animation + particle burst on submit** — A hand-rolled ~30-line
  canvas particle burst (`particles.js`), removed once faded, no persistent
  DOM (`JUICY_PUNCH`).
- **Procedural sound effects** — ZzFX-synthesised `punch` / `parry` / `fumble`
  cues and a `sessionEnd` arpeggio, tuned via `sfx-debug.php`; ~300–900 Hz for
  phone-speaker response. No asset loading (`JUICY_SOUND`, `sfx.js`).
- **Wiggle / shake on a rejected submission** — Transform-only shake on
  rate-limit / rejected / flagged / failed results (`JUICY_WIGGLE`).
- **Display entrance animation** — New wall exchanges animate in
  (`JUICY_DISPLAY_ENTRANCE`).
- **Reduced-motion respect** — Every animation is suppressed under the OS
  `prefers-reduced-motion` setting without disabling submission or consent;
  sound is gated separately by its own switch, since reduced-motion is a
  vestibular preference (`QR-07` of SE-01).
- **Compositor-only** — `opacity`/`transform` only, so effects never block the
  main thread or shift other content.
- **`sfx-debug.php`** — A live ZzFX parameter tuning page (`/sfx-debug`).
- **Shipped then reverted: Mermaid diagram rendering** — Added across display +
  input + prompt, then removed (`72a1b44`): `mermaid.min.js` was the largest
  first-load asset for a capability that saw no real use.

## 17. Internationalization (EN / DE)

- **Two UI locales** — `en` (default) and `de` (`SUPPORTED_LOCALES`), full
  catalogues in `i18n/{locale}.php`.
- **First-visit language match** — Follows the device/browser language when it
  names a supported one, English otherwise (`resolve_locale_from`, plain
  prefix match, no negotiation library) (`QR-08` of SE-01).
- **Explicit EN/DE switcher** — On every page; the choice persists via cookie
  (~1 year) and preserves the rest of the query string (`?s=`, `?o=`,
  `?debug=1`) so switching never drops session context (`localeSwitcher()`).
- **AI reply language is separate** — Handled in the prompts, follows what the
  visitor typed, not the UI locale (`config.php` note, `G-04` of SE-04).
- **German pilot seeds** — Pilot transcripts translated to German (`4bfbcc9`).
- **Catalogue-parity + fallback** — Missing key in the resolved locale falls
  back to English, never the raw key; a key missing from *both* throws
  (caught by `tests/smoke_i18n.php`).

## 18. Progressive Web App / installable

- **SE-01 web app manifest** — `app.webmanifest` (`display: standalone`,
  home-screen icon), reusing the 180×180 `apple-touch-icon.png`. Opt-in via
  the visitor's own browser chrome, never prompted (`UI-01` note).
- **SE-02 web app manifest** — `arena.webmanifest` adds
  `orientation: landscape`; lets the wall run as an installed iPad app instead
  of a projector (`C-05` of SE-02).
- **Arena touch lockdown** — Pinch-zoom, text selection, and touch callout
  disabled on the wall so a stray touch on a touchscreen can't disrupt an
  unattended display (viewport + CSS).
- **iOS status-bar / splash tuning** — (`94b0847`).
- **No service worker / offline shell** — The app needs the live backend
  regardless.
- **Cache-busting** — `fasset()` appends the file's mtime as a query string;
  no build step, no manifest, no versioning scheme.

## 19. Pilot seeding (`bin/import_pilot.php`, SE-03 `TF-06`)

- **Structured transcript import** — Creates sessions + exchanges from
  `data/pilot/*.json`, origin fixed as `pilot`, `displayable` true; performs no
  generation (the replies already exist) (`UC-01`, `SSc-03`).
- **Per-transcript validation** — A malformed transcript is rejected and named;
  the import continues with the rest, never aborts (`FA-06-1`,
  `validate_transcript`).
- **Origin fixed at creation** — Pilot vs. live is set at the moment of
  creation and no code path ever updates or infers it. Why: exhibition
  material must be extractable by filtering one stored value, never mistaken
  for evaluation data (`SG-05`, `SQR-05`, `QR-06`, `BQR-04`).
- **Optional transcript title** — Written as-is if supplied, else falls back to
  the scenario line (`FS-06-4a`).
- **Optional `reply_to` link** — A seed naming another seed links its first
  exchange to that seed's latest exchange and bumps the reply count, exactly
  as a QR reply would. Two-pass read so file order doesn't matter; a bad
  `reply_to` rejects that transcript (`FS-06-4b`, `FA-06-2`, `34cf63a`).

## 20. Operator tooling (CLI only — no admin UI)

- **No administrative interface, by constraint** — Every operator task is a
  single-purpose script run against the host. Why: build effort is capped and
  the piece is expendable; an admin UI is ruled out (`C-04` of SE-03, `SC-03`,
  `BC-01`).
- **`bin/export.php`** — Full raw JSON store dump; `--consented-only` filter.
  Also human-readable Markdown for one session, one-conversation-per-line for
  all sessions, one-turn-per-line for one session (`TODO.md`, shipped).
- **`bin/backup_db.php`** — Consistent snapshot via SQLite `VACUUM INTO`
  (correct under WAL); default `data/backups/store-<timestamp>.db`.
- **`bin/reset_db.php`** — Empties every table; `--dry-run` / `--confirm`
  guarded.
- **`bin/prune_orphaned_sessions.php`** — Targeted delete of zero-exchange
  stale sessions; `--dry-run` / `--confirm` guarded.
- **`bin/delete_session.php`** — Delete one session by id, cascading its
  exchanges and evaluation row; `--dry-run` / `--confirm` guarded.
- **`bin/import_curriculum.php` / `clear_curriculum.php` / `probe_curriculum.php`**
  — Curriculum FTS5 ingest, clear, and retrieval probe.
- **`bin/deploy.sh`** — rsync + SSH to the EasyEngine prod site, then
  `composer install` inside the site container; `--dry-run` preview. Host/path
  live in git-ignored `bin/deploy.env`.
- **Confirmation-flag guards instead of auth** — Destructive scripts require an
  explicit `--confirm`; the operator path isn't publicly routable, so no auth
  mechanism is needed (`C-03`, `C-04` of SE-03).
- **`bin/extract_*.py`** — One-off curriculum-source extractors (DDP handbook,
  Digitalentwurfslehre).

## 21. LLM provider abstraction

- **Provider dispatch** — `LLM_PROVIDER` selects `anthropic` (default) or
  `openai`; an unrecognised value fails loudly rather than silently falling
  back (would burn Anthropic credits unseen). `createLlmClient()`.
- **OpenAI-compatible / local models** — The `openai` path is any
  Chat-Completions endpoint, so it also covers a local model via LM Studio
  (`OPENAI_BASE_URL`, key optional).
- **Per-role models** — `GENERATION_MODEL` = `claude-sonnet-5`;
  `CLASSIFICATION_MODEL` = `claude-haiku-4-5` (cheaper, re-verified against the
  moderation prompt's own examples). OpenAI equivalents default to
  `gpt-4.1` / `gpt-4.1-mini`, env-overridable for local IDs.
- **Prompt caching** — The 7-section system prompt is sent as one
  `ephemeral`-cached block; repeat turns in a session get a cached-read
  discount. Turn-1 curriculum excerpts travel as a *separate, deliberately
  uncached* block so they never break that discount (`C-03` of SE-04).
- **Extended thinking disabled** — On both LLM calls (fixed a real bug + cut
  wasted cost).
- **Pure helper functions** — Response parsing and failure classification are
  pure and independently tested (`smoke_llm_client.php`).

## 22. Security hardening

- **Credential never leaves the host** — Read from host config, absent from
  version control, never in a response body, error message, or log line
  (`QR-04` of SE-03, `SQR-04`).
- **All logic server-side** — No generation, moderation, rate limit, or
  session policy in a client; any behaviour change is a backend change
  (`AP-01`, `C-01` of SE-01).
- **Text-only rendering on both clients** — No path renders backend content as
  markup (`QR-04` of SE-01, `QR-05` of SE-02).
- **QR SVGs built element-by-element** — `createElementNS` + one `<rect>` per
  module, no `innerHTML` (`qr-render.js`).
- **Prompt-injection defence** — Classifier/title prompts delimit the
  contribution as data and strip delimiter tags (`C-06`).
- **Generic error on infrastructure fault** — An uncaught exception on any
  public endpoint returns a generic JSON error (API) or the plain HTML error
  page (pages) — no path, stack trace, or exception message. CLI scripts keep
  the real trace on stderr (`QR-11`, `config.php` exception handler).
- **Whitelist validation at trust boundaries** — `?o=` and `?lang=` resolve an
  untrusted value against a fixed literal map / known-good list; anything else
  becomes null / the default.
- **Docroot separation** — Only `public/` is served; `src/`, `prompts/`,
  `data/`, `config.php` sit outside the web root on the host.

## 23. Error handling & graceful degradation

- **Every visitor-facing failure is stated** — Rate limit, turn limit, flagged
  content, generation failure, session-unknown, and client timeout each
  produce a plain message; the field re-enables (or the session presents as
  complete) as appropriate, with the typed text preserved where recovery is
  possible (`QR-02` of SE-01, `SQR-07`, `EX-02-*`).
- **Router 404 / 500 page** — `¯\_(ツ)_/¯` + code + human reason + a link
  back; used for unmatched routes and uncaught page faults
  (`renderErrorPage`).
- **Trailing-slash canonicalisation** — `/dojo/` redirects to `/dojo`.
- **Wall never clears on backend loss** — (see §7).
- **Title / evaluation failures are silent** — Best-effort background calls;
  the header just stays "Untitled", a failed eval submit is invisible. Why:
  these aren't the turn itself (`TF-06`, `TF-07` of SE-01).

## 24. Diagnostics / debug mode

- **`?debug=1` panel on `/dojo` and `/arena`** — Session id, origin,
  rate-limit remaining, generation timing, and the exact moderation reason on
  a flagged contribution. Absent by default, no visitor-facing affordance
  hints at it. Why: the operator's stated need to diagnose remotely without
  an admin interface (`QR-06` of SE-01, `QR-07` of SE-02).
- **Cheap extra fields always returned** — The backend has no server-side
  "mode"; it always returns rate-limit-remaining, generation time, and
  moderation reason, and the client decides whether to show them
  (`CLAUDE.md` conventions, `TI-01`/`TI-02`).

## 25. Prompt-quality evaluation suite (`evals/sparring/`)

- **Multi-turn simulated-visitor harness** — `bin/eval_sparring.php` runs a
  pinned opener + an argue-back persona for several turns against the *exact*
  generation payload production sends (`AnthropicLlmClient::generateResponse`),
  via real billed Anthropic calls. Separate from `tests/run.sh`, which never
  calls a real LLM.
- **Six scenarios** — `wicked-problem`, `agentic-education`,
  `feasibility-desirability` (real pilot openers), `adversarial-argue-out-of-
  stance` (`AP-07`), `meta-question` (`<edge_cases>`), `german-language`.
- **3 reps + pass-rate reporting** — Three nondeterministic layers stack
  (visitor sim, generation, judge), so a single run is noise; report pass
  rate, not a boolean.
- **Mechanical grading** — Banned phrases, length (≤2 paragraphs / ~120
  words), more-than-one-question-per-turn — plain code, zero variance
  (`bin/grade_sparring.php`).
- **LLM-judge grading** — Handed-over conclusion, held-position vs.
  obstruction vs. capitulation, premature closure, meta-commentary,
  domain-grounding precision, voice fit — against `rubric.md`.
- **Programmatic move-repetition check** — Per-turn Socratic-move labels from
  the closed 14-item vocabulary (`socratic-moves.json`), then adjacent
  repetition checked in code (more stable than a holistic judgment).
- **Baseline arm** — `--baseline` runs the bare model (no system prompt) as a
  comparison column; the *real* iterating baseline is the previous
  `sparring.md` via `git show`.
- **`bin/summarize_sparring.php`** — Per-scenario / per-arm pass-rate markdown
  table.

## 26. Testing infrastructure

- **Assert-based smoke tests, no framework** — `tests/run.sh` runs 12 (PHP
  then Node, each its own process), stops on first failure, needs no API key.
- **PHP suites** — `smoke_store.php`, `smoke_llm_client.php` (provider
  dispatch + pure helpers + turn-1 grounding attachment),
  `smoke_sparring.php` (gates, session state, expiry, rate-limiter origin
  resolution), `smoke_domain.php` (scenario derivation, delimiter stripping,
  transcript / curriculum-file parsing), `smoke_curriculum_retrieval.php`
  (retrieval quality against committed fixture excerpts).
- **Node suites** — `smoke_juicy.js`, `smoke_identity.js` (alias/avatar seed),
  `smoke_dojo.js` (contribution-outcome decision table), `smoke_arena.js` (the
  wall's add/update/remove diff), `smoke_sfx.js` (note-duration / chord
  logic).
- **Committed curriculum fixtures** — Small verbatim excerpts of the gitignored
  real corpus, so retrieval tests are reproducible on a fresh checkout.
- **`php -l`** — The only lint step (no linter configured).
- **Conventional Commits hook** — `.githooks/commit-msg`, wired by
  `composer install`.

## 27. Design documentation system (`spec/`)

- **Four-level framework** — L1 Solution / L2 System / L3 Element (one per
  SE) / LX Realization, every item carrying a traceable ID (`BG-`, `SG-`,
  `SE-`, `UC-`, `TF-`, `QR-`, `C-`, …).
- **AsciiDoc → HTML build** — `bin/build_spec.sh` renders to `public/spec/`,
  served at `/spec`.
- **The system prompt is modelled as a first-class element** — `SE-04`
  (`L3-SE-04-system-prompt.adoc`), despite having no runtime interface — it's
  almost entirely decisions.
- **Deliberate non-modelling** — Static pages and single-purpose CLI ops
  scripts are intentionally unmodelled, covered by generic constraint
  language (`C-04` of SE-03).
- **Prompt changelog** — `prompts/CHANGELOG.md`; edits to `sparring.md` are
  logged under `## Unreleased`, moved under a dated/hash heading on commit.

## 28. Public content & brand (cosmetic)

- **Landing page (`/`)** — Wordmark, スパーリング subtitle, layered boxing-glove
  graphic, tagline, alpha notice, CTA into `/dojo`. Respects safe-area insets.
- **Playful start + display design** — (`TODO.md`, shipped) — videogame /
  90s-arcade / martial-arts flavour, deliberately countering an
  "academic"-looking interface without going over the top.
- **Philosophy page (`/philosophy`)** — Thesis-context prose + image.
- **Credits page (`/credits`)** — Exhibition dates/hours, author bio, and
  full third-party acknowledgements (ZzFX, qrcode-generator, RethinkSans,
  logo fonts, Material Design Icons, EasyEngine, SQLite, Figma, Claude API).
- **Privacy & Terms pages** — Realistic content (`/privacy`, `/terms`),
  intentionally unmodelled static pages.
- **Shared page chrome** — One `pageHeader()` / `pageFooter()` helper for the
  four content pages (logo + way back to `/` + locale switcher).
- **Open Graph / Twitter Card** — One `ogTags()` helper (domain + share image
  in one place); 1200×630 share image.
- **Custom typography** — RethinkSans ExtraBoldItalic (self-hosted woff2),
  Darumadrop One + Mochi Boom in the logo.
- **Theme colour + favicon + apple-touch-icon** — Shared `webAppTags()`
  helper.
- **ASCII-art source banner** — `sillyBanner()` — an ASCII "Sparring" comment
  at the foot of every page. Not a feature; makes View Source more fun.
- **Custom in-page confirm dialog** — Replaces `window.confirm()` with a
  gate-card so the flow stays scriptable and consistent (native confirm
  blocks the whole page).

---

## Cross-cutting rationale (recurring "why"s)

- **Everything is expendable and capped** — `BC-01` / `SC-03`: build effort
  must never compete with thesis writing; abandoning the installation
  entirely must not jeopardise the thesis. This is why there's no build step,
  no admin UI, no analytics layer, no framework, one SQLite file, and CLI
  scripts instead of tooling.
- **Runs unattended for days** — `BG-03` / `SQR-02`: no failure mode may need
  a person in the room. Hence polling over push, auto-restart with no
  in-memory state, in-place rate-window reset, the wall never clearing, and
  the never-empty pilot fallback.
- **The friction is the exhibit** — `BG-01` / `AP-07`: a visitor trying to
  argue the AI out of its stance is running the experiment, not misusing it.
  Hence no off-topic filter, moderation that rejects a turn without ending
  the session, and a prompt that meets pressure with more challenge.
- **GDPR on public user-generated content** — `BC-02` / `SC-05`: three
  separate consent decisions, projection gated at write time, retention and
  projection each a genuine opt-out, nothing identifying ever able to reach
  the wall.
- **Legible from across the room** — `BG-01` / `SG-02`: the exchange pair as
  the display unit, a short reply register in the prompt, uniform item shape,
  no-flicker reconciliation.
