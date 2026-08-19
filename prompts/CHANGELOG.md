# Changelog — prompts/sparring.md

Tracks changes to the Sparring system prompt. No tags/versions yet — entries
keyed by date + commit.

## Unreleased
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
