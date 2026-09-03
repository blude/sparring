# docs/ — generated diagrams

Print-ready A3 SVGs about the Sparring project, plus the scripts that build
them. Everything here is vector, has selectable/editable text, uses no
browser and no npm dependencies (Node's stdlib + the `git` binary only), and
is **deterministic** — same inputs and same seed produce byte-identical
drawing content.

```
docs/
  features.md                     feature inventory (prose, hand-maintained)
  ios-keyboard-viewport.md        engineering note: the dojo layout vs. the
                                  iOS software keyboard (prose, hand-maintained)
  stamp.mjs                       shared filename timestamp (see Filenames)
  feature-cloud/                   word clouds of the feature inventory
  spec-network/                   traceability network of the spec/ requirements
  commit-punchcard/               commit activity by weekday × hour
```

`spec/` (the AsciiDoc design docs) is the input for `spec-network/`; it is
not part of this folder.

## Filenames

Every generator stamps its output: `<base>.<UTC YYYYMMDD-HHMMSS>.svg`
(from `docs/stamp.mjs`). Each run archives a new file instead of
overwriting the last render, so several dated versions can sit side by side
in git. Old ones are **not** auto-deleted — prune by hand when you don't
need them. Examples below use `<stamp>` for the timestamp part.

---

## feature-cloud/ — feature inventory word clouds

`cloud.gen.mjs` → `words.{portrait,landscape}.<stamp>.svg`,
`titles.{portrait,landscape}.<stamp>.svg`

Two Archimedean-spiral word clouds built from the 197 feature titles that
also live in `docs/features.md` (the list is inlined in the script, not read
from the file):

| File | What |
|---|---|
| `words.*` | titles split into terms, sized by how often each term recurs (top ~150) |
| `titles.*` | each title kept whole, shorter/punchier ones sized larger |

**Usage**

```
node docs/feature-cloud/cloud.gen.mjs [seed] [--landscape]
```

- `seed` — integer, default `2`. Layout is deterministic per seed; try a few
  and keep whichever composes best (`node docs/feature-cloud/cloud.gen.mjs 7`).
- `--landscape` — render landscape instead of the default A3 portrait
  (297×420 mm). Orientation is in the filename, so both can coexist.
- Text width is estimated from a rough per-glyph table (no font metrics
  headless); collision padding absorbs the error. Font stack is `SF Pro Text`
  with a sans-serif fallback.

---

## spec-network/ — requirement traceability network

Both generators read the same graph, parsed once in **`graph.mjs`**.

### The graph (`graph.mjs`)

Parses the six AsciiDoc docs in `spec/` (L1 solution, L2 system, L3 ×4
elements) into nodes and directed edges. Not run directly — imported by the
two renderers. Runs a self-check on import and throws if the parse looks
wrong (node count out of band, no cross-level edges, known edges missing).

- **Nodes** — every top-level requirement / element ID (`BG-`, `SG-`, `G-`,
  `TF-`, `QR-`, …). Excluded: step anchors (`FS- FA- ST- EX- PS- PA- SA-`),
  entity attributes (`E-01.8`), and open questions (`TBC-`). ~191 nodes.
- **Edges** — only the typed traceability lines: `_Satisfies:_`,
  `_Realises:_`, `_Refines:_`, `_Achieves:_`, `_Supports:_`, `_Applies to:_`,
  `_Implements:_` (plus a few minor ones). Prose mentions of an ID are not
  edges. ~320 edges, ~94 crossing design levels.
- **Namespacing** — L3 element docs restart their numbering (each `SE-0x`
  file has its own `G-01`, `TF-01`, …), so a node id is `<file key>:<id>`,
  e.g. `SE-03:TF-01`. Same-doc `<<…>>` links resolve locally; cross-doc
  `xref:file#ID[…]` links resolve to that file.

### `network.gen.mjs` — block layout

`node docs/spec-network/network.gen.mjs` →
`network.{landscape,portrait}.<stamp>.svg`

Shelf-packed blocks, one per `(document, prefix)` pair, ordered L1 → L2 → L3.
Node labels are the bare ID. Landscape and portrait are two different
packings of the same graph (wide vs. narrow pack width), not one rotated. No
flags. Deterministic.

### `force.gen.mjs` — force-directed layout

```
node docs/spec-network/force.gen.mjs [seed] [--by-type]
```

Physics simulation (Fruchterman–Reingold: O(n²) repulsion + per-edge springs
+ degree-weighted gravity) → `force.{landscape,portrait}.<stamp>.svg`.

- Every ID is a point; every trace link an edge.
- Point radius grows with degree (connection count).
- Only hubs (degree ≥ 6, ~30 points) get a floating label, on a white
  backing rect for legibility over the edges.
- The 16 edgeless requirements are held out of the sim and listed in a
  caption.
- `seed` — integer, default `20260901`. Deterministic per seed; each
  orientation runs its own sim and stretches to fill the sheet.
- `--by-type` — colour points by requirement prefix instead of by design
  level.

**Colours** (both generators): L1 = Sparring brand red `#d32f2f` (the
wordmark colour, `public/assets/arena.css --brand-red`); L2 = deep teal
`#0f8b8b`; L3 = violet `#6b4fc9`. Red/green pairing is avoided for
colour-blind legibility.

---

## commit-punchcard/ — commit activity

`punchcard.gen.mjs` → `punchcard.<stamp>.svg`

`node docs/commit-punchcard/punchcard.gen.mjs`

A3 portrait. 24 hour rows down the Y axis, 7 weekday columns across; one
circle per `(weekday, hour)` cell with **area ∝ commit count**. Reads the
current branch's history via `git log`, by author date. No flags.

It is a **snapshot** of history as it stands when you run it — the commit
count and date range in the subtitle update every time. Re-run and re-commit
when you want a fresh card. Deterministic for a given repo state.

---

## Regenerating

```
node docs/feature-cloud/cloud.gen.mjs
node docs/spec-network/network.gen.mjs
node docs/spec-network/force.gen.mjs
node docs/commit-punchcard/punchcard.gen.mjs
```

Each script writes its SVG(s) next to itself (a fresh `<stamp>` per run) and
prints a one-line summary naming the files it wrote. Delete the superseded
dated files you don't want to keep, then commit.

## Editing in Illustrator

The SVGs open as editable text (plain `<text>`, no outlined paths, no
`paint-order` strokes). `feature-cloud/` uses an `SF Pro Text` stack;
`spec-network/` and `commit-punchcard/` use a `Helvetica Neue, Arial` stack
that substitutes cleanly.
