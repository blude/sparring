# 15. Spec PDF book target pre-renders Mermaid instead of an Asciidoctor plugin

- **Status:** Accepted
- **Date:** 2026-09-10
- **References:** `bin/build_spec_pdf.sh`; `spec/book.adoc`; ADR
  [0001](0001-vanilla-php-sqlite-no-framework.md) (no build step); ADR
  [0002](0002-four-level-design-framework.md) (the spec itself)

## Context

The spec was HTML-only (`bin/build_spec.sh` → `public/spec/*.html`, one page
per L1–LX file, committed). A combined PDF was wanted for printing and
offline reading of the whole design record as one book.

Three problems:

1. **Combining.** Each `spec/*.adoc` is a standalone document with its own
   `= Title`, author line, `:toc:` header, and an `xref:index.adoc` breadcrumb
   — none of which belong in a bound book.
2. **Colliding IDs.** Requirement IDs are only unique within a file: every L3
   element numbers its entities `E-01.1`, `E-01.2`, …, so merging the files
   makes Asciidoctor see the same anchor defined several times (it keeps the
   first and warns). Cross-file links (`xref:other.adoc#ID[]`) also stop
   resolving once there is no `other.adoc` to point at.
3. **Mermaid.** Eight `[source,mermaid]` blocks across the files. The HTML
   edition renders them client-side with `mermaid.min.js` in a docinfo footer;
   a PDF has no browser. Asciidoctor has no native Mermaid renderer, and
   `asciidoctor-diagram` / `asciidoctor-kroki` are plugin gems. Homebrew's
   `asciidoctor` pins `GEM_HOME` to a sealed, versioned directory
   (`Cellar/asciidoctor/<ver>/libexec`) that `brew upgrade` replaces, so a
   plugin gem installed there does not survive an upgrade, and installing into
   the user gem tree is not on the load path of the Homebrew binary.

## Decision

Add `bin/build_spec_pdf.sh`, separate from `build_spec.sh` (the HTML build is
fast and runs often; this one launches a headless browser per diagram and
takes ~30s). It:

- copies `spec/*.adoc` to a temp dir (sources never touched), drops
  `index.adoc`;
- per file: strips the standalone header (lines 2–9) and the
  `xref:index.adoc[Index] / …` breadcrumb, gives the chapter a stable id
  (`chap-L1`, `chap-SE-03`, …), and prefixes every anchor **definition** and
  every same-file `<<ID,…>>` reference with a short per-file tag
  (`[[E-01.1]]` in the display client becomes `[[SE-02-E-01.1]]`);
- across all files: rewrites `xref:other.adoc#ID[…]` to the now-local
  `xref:PREFIX-ID[…]`, and bare `xref:other.adoc[…]` to `xref:chap-PREFIX[…]`;
- pulls each `[source,mermaid]` block out to a `.mmd` file, renders it to
  **PNG** with `npx --yes -p @mermaid-js/mermaid-cli@11 mmdc -s 2`, and
  rewrites the block to `image::diag-N.png[]`;
- renders `spec/book.adoc` with plain `asciidoctor-pdf` to
  `public/spec/sparring-spec.pdf` (gitignored — unlike the committed HTML,
  it is a rebuildable binary).

Only link *targets* are rewritten, never labels — every `<<>>`/`xref:` in
these files already carries an explicit label — so the rendered text is
identical to the standalone HTML. The build is warning-free and internal
navigation (both repeated IDs and cross-chapter links) resolves.

`spec/book.adoc` is a thin master: book-doctype header, front matter lifted
from `index.adoc`, then `include::…[leveloffset=+1]` per file (the `= Title`
on line 1 becomes a chapter).

### Why these choices

- **Pre-render, not a plugin.** Sidesteps the Homebrew `GEM_HOME` problem
  entirely and keeps the toolchain to what's already expected (`asciidoctor`,
  Node). Do not re-attempt `-r asciidoctor-diagram` / `-r asciidoctor-kroki`
  without first solving where the gem lives.
- **`npx`, not a committed dependency.** mermaid-cli is dev-time tooling for a
  manual build target, fetched on demand. Consistent with the project having
  no `package.json`; `build_spec.sh` already shells out to a non-committed gem.
- **PNG, not SVG.** Mermaid flowcharts emit HTML `<foreignObject>` labels;
  asciidoctor-pdf's SVG renderer draws those blank. PNG at `-s 2` avoids it.
- **Prefix targets in a preprocess pass, don't edit the sources.** The anchor
  namespacing is a text rewrite on the temp-dir copies, driven by a
  filename→prefix map in the script. The `spec/*.adoc` files keep their
  file-local IDs, which is what the HTML edition wants.

## Consequences

- `bin/build_spec_pdf.sh` needs network on first run (npx fetches mermaid-cli
  and a headless Chromium).
- The HTML edition stays canonical. The book is a second view of the same
  source, not a new source.
- The anchor-prefix rewrite assumes the file-local conventions actually held:
  IDs match `[\w.-]+`, anchors are the `[[ID]]` form, `<<…>>` is always
  same-file, and cross-file links always use `xref:file.adoc#…`. All true at
  time of writing (checked across every `spec/*.adoc`). A new file, or a
  `<<…>>` that points at another file, would need the map and the assumptions
  revisited.
- A new `spec/*.adoc` file must be added to `book.adoc`'s include list **and**
  to the `prefix_for` / `%pfx` maps in the script.
