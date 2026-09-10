# 2. Four-level design framework as the design record

- **Status:** Accepted
- **Date:** Project inception (back-filled 2026-09-10)
- **References:** `spec/index.adoc`; `CLAUDE.md` "spec/ — design documentation"

## Context

This is a master's thesis project; the design reasoning is itself a
deliverable, not just scaffolding for the code. It needs a record that
traces every technical choice back to a stated need, and that a reader
can enter at the level they care about.

## Decision

Keep the design record in `spec/` as a four-level framework: L1 Solution
Design Concept (why build it), L2 System Design Concept (the installation
as one system, its architecture and elements), L3 Element Design Concept
(one document per element: SE-01 input client, SE-02 display client,
SE-03 backend, SE-04 system prompt), and LX System Realization Concept
(deployment reality). Every item carries a typed ID (`BG-`, `SG-`, `SE-`,
`UC-`, `TF-`, `QR-`, `C-`, `AP-`, …) and traces to a parent one level up.
Written in AsciiDoc, built to `public/spec/` with `bin/build_spec.sh`.

No L0 Digital Design Brief was produced, so the record starts at L1 and
L1's constraints trace to nothing above them.

Only behavioural and decision content is modelled. Static pages and
single-purpose CLI ops scripts are intentionally left unmodelled, covered
by generic constraint language (`C-04` in the SE-03 document) rather than
a dedicated node that would restate its parent.

## Consequences

- New work touching visible behaviour has to be checked against `spec/`;
  behavioural edits to `prompts/sparring.md` are checked against `SE-04`.
- The spec answers "what must be true and why", not "how the code is
  written". Implementation-level and reversible engineering decisions had
  no home — this ADR log (`docs/adr/`) is that home, and cites `spec/`
  items where a decision follows from a stated principle.
- AsciiDoc plus a build script is one more thing to run, accepted for the
  cross-referencing and HTML output the format gives.
