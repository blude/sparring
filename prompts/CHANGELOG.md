# Changelog — prompts/sparring.md

Tracks changes to the Sparring system prompt. No tags/versions yet — entries
keyed by date + commit.

## Unreleased
- prompt: `<core_mechanism>` renames Socratic/Sparring "moves" to
  "approaches"/"strategies", allows combining one of each per turn
  (example added), and loosens the anti-repetition rule from no two
  consecutive turns to no more than two in a row.
- prompt: remove `<boundary_objects>` section entirely — no more Read/
  WebFetch/WebSearch artifact-anchoring instruction, no more generated
  maps/tables/templates, no more request for the visitor to produce one
  themselves. `spec/L3-SE-04-system-prompt.adoc` updated to match: dropped
  TF-02 (Work with boundary objects), the FS-01-4 call into it, and
  `<boundary_objects>` from C-04's tag list; renumbered the curriculum-
  grounding function from TF-03 to TF-02.
- prompt: `<edge_cases>` meta-question answer reworked — open by grounding
  Sparring's name in martial arts and build the bridge to argumentative
  debate together, instead of going straight to the practice-fight
  analogy. "how it maps to design work" narrowed to "design argumentation".
- prompt: `<voice>` adds a block on varying how Popov pulls for a
  response, not always with a trailing "?" — embedded question as fact,
  imperative, trailing off, invite correction, modal softening.
- prompt: `<identity>` renames the AI from "Sparring" to "Popov, an AI
  sparring partner", and adds a line placing Popov inside Sparring, the
  platform.
- prompt: `<identity>` "one job" line reworded from "help users practice"
  to "engage with users to practice".
- prompt: `<identity>` states a hard session limit — 16 turns or less, no
  more messages accepted after.
- prompt: `<voice>` tone rewritten from "curious, quick-witted" to "curious,
  casual, vibrant, sporty" and specifically interested in what the user
  (not "they") thinks and why.
- prompt: ban em-dashes outright; use ellipsis `…` sparingly for a thinking
  pause, a change of strategy, or a repositioning mid-turn, not as a default
  between every sentence.
- prompt: add vocatives/interjections ("Look, ", "Wait! ", "I mean, ",
  "Sure, ", "A-ha! ") to open turns, connecting to the learner's previous
  message and mirroring an emotional reaction.

## 2026-08-23 — `72a1b44` perf: remove mermaid diagram rendering support
- prompt: remove Mermaid diagram generation from `<boundary_objects>` —
  dropped the "draw a small diagram" instruction and the six worked
  examples (flowchart/sequence/class/state/ER/pie/quadrant). Mermaid
  rendering support is removed client-side too (`mermaid.min.js` was the
  single largest asset on first load, for a capability that saw no real
  use); the prompt no longer tells Sparring to produce a fence nothing
  renders.

## 2026-08-20 — `f90f7a1` feat(prompts): reply/title in the visitor's own message language
- prompt: instruct Sparring (`<voice>`/"How to respond") and the title
  generator (`prompts/title.md`) to reply/title in the same language the
  visitor's contribution is written in (English or German) — part of
  adding German UI support alongside English. The AI's reply language
  follows what the visitor typed, not the resolved UI locale toggle.

## 2026-08-18 — `73583bd` prompt: add edge case rule for meta questions
- Added edge-case rule: meta questions ("what is Sparring," "how does this
  work") get the practice-fight analogy directly, without explaining how it
  maps to design work — ask the visitor to put the mapping in their own
  words or bring something real instead.

## 2026-08-17 — `cb7d847` prompt: reorganize sections
- Reordered/renamed XML sections (`pedagogical_intents`, `domain_grounding`,
  `boundary_objects`, `edge_cases` regrouped), no content change.

## 2026-08-17 — `d46f283` prompt: considerably expand the system instructions
- Rewrite: identity now names the exhibition context explicitly (Semesterausstellung).
- Added explicit 4-stage `core_mechanism` (mine argument → pick strategy →
  generate question → hold dialogue), stages 1–3 silent, only stage 4 visible.
- Added move table (assumption surface, counter-example, scope probe, evidence
  demand, reframe, consistency check, completion demand, scope reduction) —
  one move per turn, varied.
- Added `<voice>` styleguide (1–2 paragraphs, no recap, no meta-commentary).
- Expanded `<anti_sycophancy>` word list ("move", "failure mode" added).
- Removed old "push on the specific claim" instruction (folded into stage 1/2).

## 2026-08-16 — `3cd92c1` feat: render mermaid diagrams in sparring partner responses
- Sparring can draw a small mermaid-fenced diagram when a structural/relational
  point calls for it, rendered on both SE-01 (phone) and SE-02 (wall).

## 2026-08-16 — `fd37a48` prompt: organize sparring prompt into XML tags
- Reorganized prompt into XML-tagged sections (`<identity>`, `<core_mechanism>`,
  `<voice>`, `<anti_sycophancy>`, etc.).

## 2026-08-06 — `54e6d7c` Fix moderation over-flagging + retention dialog dead space
- Trimmed 7 lines (moderation-related over-flagging fix).

## 2026-08-06 — `c4010c3` POC: backend service, input/display clients, LLM integration, pilot spec
- Initial prompt (41 lines).
