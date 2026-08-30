#!/usr/bin/env python3
"""
Extract the IREB "DDP Foundation Level" Handbuch (v2.0.1, de, 159 pp.) from PDF
to a clean markdown file + a ~700-token JSONL chunk set for RAG.

Format-independent machinery (chunking, markdown assembly, hyphenation,
tokenizing) lives in bin/inc/curriculum_pdf.py and is shared with
bin/extract_digitalentwurfslehre.py. This file keeps only what is specific to
the DDP handbook: line_info(), heading_level(), parse_pages().

Why the heuristics look the way they do (from probing the actual file):
  - Microsoft Word export, single column, A4 (595x842). Every body page carries
    one decorative footer image at y 806-841 — ignored (we never read images).
  - Running FOOTER only, no header: "DDP | Handbook| © IREB" + "N | 159" in
    GTPressuraMono 10pt at y0~821. Dropped by y-band (y1 > 800) AND by loose
    string match (footer spacing is irregular, e.g. p159 "DDP | Handbook | ©").
    Body text starts as high as y0~43, so there is NO header band to trim.
  - Body text is PPMori-Regular 11pt; italic is PPMori-RegularItalic.
  - Headings are NOT bold-flagged (the bold bit never appears in this PDF).
    They are the only GTPressuraMono runs at >= 13pt: 20pt = chapter (a bare
    number line + a title line, same block), 14pt = section AND subsection —
    same size, so "1.1" vs "1.1.1" is told apart only by the number pattern;
    the title line carries no number, so a wrapped heading's level comes from
    whichever of its lines has the number.
  - GTPressuraMono at 11-12pt is a definition callout box, at 9pt is a figure/
    table caption ("Abbildung N.N", "Tabelle N.N") — both kept as body.
  - Bullets: "▪" is a Wingdings-Regular glyph on its own line, its text on the
    following line(s). Each "▪" starts a new "- " list item.
  - Footnotes: PPMori 8pt at page bottom, with a 5pt leading digit — dropped.
    Inline footnote-reference digits are 5-6.5pt superscript spans — dropped in
    line_info() via the superscript flag.
  - Front matter (cover, Nutzungsbedingungen, Versions-Historie, dot-leader
    Inhaltsverzeichnis) is pp. 1-7; the trailing Literaturverzeichnis is
    pp. 153+. Default range is therefore pages 8-152 (chapter 1 to end of the
    last content chapter).

Needs:  pip install pymupdf tiktoken

Usage:
  python bin/extract_ddp_handbook.py                  # pages 8-152
  python bin/extract_ddp_handbook.py --pages 8-20     # subset, to eyeball
  python bin/extract_ddp_handbook.py --src PATH --out DIR
"""
import argparse
import re
import sys
from pathlib import Path

import pymupdf

# bin/inc/ holds the shared, format-independent extraction machinery.
sys.path.insert(0, str(Path(__file__).resolve().parent / "inc"))
from curriculum_pdf import (  # noqa: E402
    DOTLEADER_RE,
    clean_text,
    join_hyphenated,
    parse_page_range,
    run_extraction,
)

# Paths default relative to the repo root (this file lives in <repo>/bin/).
# data/curriculum/ is gitignored — the source PDF must be placed there.
REPO = Path(__file__).resolve().parent.parent
SRC = REPO / "data/curriculum/ddp_foundationlevel_handbook_de_v2.0.1.pdf"
OUT_DIR = REPO / "data/curriculum/ddp-handbook-content"
STEM = "ddp-handbook"
ID_PREFIX = "ddp-"

FOOTER_Y = 800.0                 # drop any line whose bottom is below this
HEADING_FONT = "GTPressuraMono-Regular"
HEADING_MIN_SIZE = 13.0          # 11-12pt mono = definition box, 9pt = caption
BULLET_FONT = "Wingdings-Regular"

# footer text, belt-and-suspenders with the y-band: "DDP | Handbook| © IREB"
# and the "N | 159" page marker (end-anchored so real "3 | 4"-ish prose survives)
DROP_LINE_RE = re.compile(r"^(?:DDP\s*\|\s*Handbook|\d+\s*\|\s*\d+\s*$)")
NUM_RE = re.compile(r"^\d+(?:\.\d+)*\b")     # "1", "1.1", "1.1.1 ..."
NUM3_RE = re.compile(r"^\d+\.\d+\.\d+")
NUM2_RE = re.compile(r"^\d+\.\d+(?!\.)")


def line_info(line):
    """(text, max_size, fonts:set) for a PyMuPDF line, or None if empty.

    Superscript spans (bit 1 of flags) are the inline footnote-reference
    digits — dropped here so "Digital Design1 als" becomes "Digital Design als"
    while the surrounding spaces (whitespace-only spans) are kept.
    """
    keep = [s for s in line["spans"] if not (s["flags"] & 1)]
    visible = [s for s in keep if s["text"].strip()]
    if not visible:
        return None
    text = "".join(s["text"] for s in keep)
    size = max(s["size"] for s in visible)
    fonts = {s["font"] for s in visible}
    return text, size, fonts


def heading_level(text, size, fonts):
    """Markdown heading level 1-4 for a line, or 0 if it's body text.

    A heading line is pure GTPressuraMono at >= 13pt. 20pt = chapter. At 14pt,
    section vs subsection is the number pattern ("1.1" vs "1.1.1"); an
    unnumbered 14pt head falls back to 3 (never 2 — a 2 would let the block's
    min() demote a real "N.N.N").
    """
    t = text.strip()
    if fonts != {HEADING_FONT} or size < HEADING_MIN_SIZE:
        return 0
    if size >= 18:
        return 1
    if NUM3_RE.match(t):
        return 3
    if NUM2_RE.match(t):
        return 2
    return 3


def _heading_block_level(texts, levels):
    """Level for a multi-line heading block: trust the line that carries the
    number (title lines have none); else the shallowest line level."""
    for t, lv in zip(texts, levels):
        if NUM_RE.match(t):
            return lv
    return min(levels)


def _emit_bullets(raw, page):
    """Split a block into runs, each starting at a Wingdings ("▪") line, and
    emit one "- " body item per run. A single join over the whole block would
    fuse a multi-item list into one run-on paragraph, and the leading-glyph
    strip would only catch the first marker."""
    runs = []
    for text, _size, fonts in raw:
        if BULLET_FONT in fonts or not runs:
            runs.append([])
        runs[-1].append(text)
    for run in runs:
        item = clean_text(join_hyphenated(run))
        item = re.sub(r"^[\s▪•·-]+", "", item)   # strip the marker glyph
        item = re.sub(r"[ \t]+", " ", item).strip()
        if item:
            yield ("body", 0, "- " + item, page)


def parse_pages(doc, page_range):
    """Yield ('heading', level, text, pageno) and ('body', 0, text, pageno)
    in reading order. (No ('code', ...) — this PDF has no template/ASCII blocks;
    all monospace text is footer, heading, caption or definition box.)"""
    for pno in page_range:
        page = doc[pno]
        for block in page.get_text("dict")["blocks"]:
            if "lines" not in block:
                continue
            raw = []
            for line in block["lines"]:
                info = line_info(line)
                if info is None:
                    continue
                text, size, fonts = info
                if line["bbox"][3] > FOOTER_Y:           # running footer band
                    continue
                stripped = text.strip()
                if DROP_LINE_RE.match(stripped):         # footer text
                    continue
                if DOTLEADER_RE.search(stripped):        # stray TOC leader line
                    continue
                if round(size) == 8:                     # footnote block
                    continue
                raw.append((text, size, fonts))

            if not raw:
                continue

            # bullet block: one or more "▪" runs
            if any(BULLET_FONT in r[2] for r in raw):
                yield from _emit_bullets(raw, pno + 1)
                continue

            levels = [heading_level(*r) for r in raw]

            # heading block: every line is heading-level (however many lines the
            # title wrapped to, and even if the number is its own line)
            if all(lv > 0 for lv in levels):
                texts = [r[0].strip() for r in raw]
                lvl = _heading_block_level(texts, levels)
                htext = clean_text(re.sub(r"\s+", " ", " ".join(texts))).strip()
                if htext:
                    yield ("heading", lvl, htext, pno + 1)
                continue

            # first line is a heading, rest is prose -> split them
            if levels and levels[0] > 0 and any(lv == 0 for lv in levels[1:]):
                htext = clean_text(raw[0][0].strip()).strip()
                if htext:
                    yield ("heading", levels[0], re.sub(r"\s+", " ", htext), pno + 1)
                raw = raw[1:]

            para = clean_text(join_hyphenated([r[0] for r in raw]))
            para = re.sub(r"[ \t]+", " ", para).strip()
            if para:
                yield ("body", 0, para, pno + 1)


def selftest():
    H = {HEADING_FONT}
    assert heading_level("1.1 Ein Berufsbild", 14.0, H) == 2
    assert heading_level("1.1.1 Drei Stufen", 14.0, H) == 3
    assert heading_level("Motivation für Digital Design", 20.0, H) == 1
    assert heading_level("1", 20.0, H) == 1
    assert heading_level("Digital solution: a system", 12.0, H) == 0
    assert heading_level("Abbildung 2.1 - Kompetenzprofil", 9.0, H) == 0
    assert heading_level("normaler Fließtext", 11.0, {"PPMori-Regular"}) == 0
    assert _heading_block_level(["1", "Motivation"], [1, 1]) == 1
    assert _heading_block_level(["1.1", "Ein Berufsbild", "digitaler Lösungen"], [2, 3, 3]) == 2
    assert DROP_LINE_RE.match("DDP | Handbook| © IREB")
    assert DROP_LINE_RE.match("8 | 159")
    assert not DROP_LINE_RE.match("1.1 Ein Berufsbild")
    items = list(_emit_bullets(
        [("▪", 11.0, {BULLET_FONT}),
         ("Datendigitalisierung bezeichnet den Einsatz", 11.0, {"PPMori-Regular"})], 8))
    assert items == [("body", 0, "- Datendigitalisierung bezeichnet den Einsatz", 8)], items
    print("selftest ok")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--pages", default="8-152",
                    help="1-based inclusive range (default 8-152: chapter 1 to end)")
    ap.add_argument("--src", type=Path, default=SRC, help="source PDF")
    ap.add_argument("--out", type=Path, default=OUT_DIR, help="output directory")
    ap.add_argument("--selftest", action="store_true")
    args = ap.parse_args()

    if args.selftest:
        selftest()
        return

    doc = pymupdf.open(args.src)
    rng = parse_page_range(args.pages, doc.page_count)
    run_extraction(doc, args.out, STEM, ID_PREFIX, rng, parse_pages)


if __name__ == "__main__":
    main()
