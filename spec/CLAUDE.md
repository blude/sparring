# spec/ — design documentation

Four-level framework (L0 brief, L1 solution, L2 system, L3 per-element),
ID scheme (`BG-`, `SG-`, `G-`, `UC-`, `TF-`, `QR-`, `C-`, etc.), written
in AsciiDoc and built to `public/spec/` with `bin/build_spec.sh`.
`bin/build_spec_pdf.sh` builds the same content as one combined book,
`public/spec/sparring-spec.pdf` (gitignored, built on demand — it
pre-renders the Mermaid blocks via `npx` mermaid-cli, see
`docs/adr/0015-spec-pdf-book-target.md`).
- Only behavioral/decision content gets modeled. Static pages
  (`privacy.php`, `terms.php`) and single-purpose CLI ops scripts
  (`bin/export.php`) are **intentionally unmodeled** — covered by existing
  generic constraint language (see `C-04` in `L3-SE-03-backend-service.adoc`)
  rather than a dedicated UC/TF/QR that would just restate its parent.
- `prompts/sparring.md` is modeled as `SE-04` (`L3-SE-04-system-prompt.adoc`)
  — a content-supplying element with no runtime interface of its own.
