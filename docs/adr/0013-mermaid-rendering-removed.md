# 13. Mermaid diagram rendering in responses

- **Status:** Superseded by removal
- **Date:** Added 2026-08-16 (commit `3cd92c1`), removed 2026-08-23 (commit `72a1b44`)
- **References:** `TODO.md` "Shipped then reverted"; `prompts/CHANGELOG.md`; `CHANGELOG.md` 2026-08-23

## Context

An early idea was to let the sparring partner draw diagrams. The
implementation shipped `mermaid.min.js` client-side on the input and
display clients, plus prompt instructions telling Sparring it could emit
Mermaid.

## Decision (original)

Render Mermaid diagrams in sparring-partner responses on both clients, and
instruct the prompt to produce them.

## Decision (superseding)

Remove it. `mermaid.min.js` was the largest first-load asset in the
project, for a capability that saw no real use in practice. The library,
the client wiring, and the prompt instruction were all removed; the
prompt no longer mentions diagrams.

## Consequences

- First-load weight on both clients dropped substantially.
- Sparring cannot draw diagrams. This was judged no loss — the piece is
  about argument in prose, on a phone and a wall, and diagrams were not
  landing.
- Kept here as a record so the option is not re-attempted without
  weighing the same first-load cost. If revisited, the diagram renderer
  must not be a blocking first-load asset.
