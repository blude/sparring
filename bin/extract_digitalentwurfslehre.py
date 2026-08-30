#!/usr/bin/env python3
"""
Extract "Digitalentwurfslehre" (Kim Lauenroth, manuscript V1.0, 1216 pp.)
from PDF to a clean markdown file + a ~700-token JSONL chunk set for RAG.

Format-independent machinery (chunking, markdown assembly, hyphenation,
tokenizing) lives in bin/inc/curriculum_pdf.py and is shared with
bin/extract_ddp_handbook.py. This file keeps only what is specific to the
Lauenroth manuscript: line_info(), heading_level(), parse_pages().

Why the heuristics look the way they do (from probing the actual file):
  - Single column, A4. No images. Body text is Helvetica 10pt.
  - Running header  "Digitalentwurfslehre — Kim Lauenroth"  sits at y<45 on
    every page; running footer  "Seite N"  at y>790. Both dropped by y-band
    AND by exact string, belt and suspenders.
  - Headings are bold and one of a few discrete sizes: 18 = Kapitel/Teil/
    Anhang, 15 = section, 13 = subsection, 11 = sub-subsection. Numbered
    headings ("2.3", "2.3.1") are trusted by their number pattern; unnumbered
    bold lines fall back to the size bucket. The manuscript is inconsistent
    (some prose subheads are 15pt) so a few may mis-level — acceptable for RAG.
  - Size 12 lines are stray bullet glyphs / footnote-ref digits, not text.
  - Size 8 lines are preformatted blocks (an ASCII table, a template) -> fenced.
  - Some editions' tables of contents are pure dot-leader lines ("...."); any
    line with 4+ consecutive dots is a TOC artifact and dropped everywhere.
    This manuscript's TOC (pp. ~2-10) has NO leaders — its chapter entries are
    styled byte-identically to the real chapter headings, so nothing but the
    page range separates them. Drop that span with --skip 2-10.
  - The author left HTML-comment placeholders ("<!-- ... -->") in the text;
    stripped. Text is HTML-unescaped ("&amp;" -> "&").

Needs:  pip install pymupdf tiktoken

Usage:
  python bin/extract_digitalentwurfslehre.py                 # full book
  python bin/extract_digitalentwurfslehre.py --pages 12-60   # subset, to eyeball
  python bin/extract_digitalentwurfslehre.py --skip 2-10     # full book minus TOC
  python bin/extract_digitalentwurfslehre.py --src PATH --out DIR
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
    sentence_split,
)

# Paths default relative to the repo root (this file lives in <repo>/bin/), so
# the script is runnable on any checkout. data/curriculum/ is gitignored — the
# source PDF must be placed there (or passed with --src).
REPO = Path(__file__).resolve().parent.parent
SRC = REPO / "data/curriculum/digitalentwurfslehre-raw.pdf"
OUT_DIR = REPO / "data/curriculum/digitalentwurfslehre-content"

HEADER_Y = 45.0        # anything with top above this is the running header
FOOTER_Y = 790.0       # anything with bottom below this is the running footer

DROP_LINES = {"Digitalentwurfslehre — Kim Lauenroth"}
SEITE_RE = re.compile(r"^Seite \d+\s*$")
NUM3_RE = re.compile(r"^\d+\.\d+\.\d+")
NUM2_RE = re.compile(r"^\d+\.\d+(?!\.)")
CHAPTER_RE = re.compile(r"^(Kapitel|Teil|Anhang)\b")


def line_info(line):
    """(text, max_size, all_bold) for a PyMuPDF line dict, or None if empty."""
    spans = [s for s in line["spans"] if s["text"].strip()]
    if not spans:
        return None
    text = "".join(s["text"] for s in line["spans"])
    size = max(s["size"] for s in spans)
    # bit 4 (value 16) of span flags = bold; treat line as a heading candidate
    # only if every visible span is bold
    all_bold = all(bool(s["flags"] & 16) for s in spans)
    return text, size, all_bold


def heading_level(text, size, all_bold):
    """Markdown heading level 1-4 for a line, or 0 if it's body text.

    Numbered headings ("Kapitel N", "2.3", "2.3.1") carry the real 1/2/3
    structure and are trusted. Unnumbered bold lines are demoted to >=3:
    the manuscript styles many mid-section subheads at the same 15pt as
    numbered sections, so size alone can't tell them apart -- treating every
    unnumbered head as a subhead keeps the section_path hierarchy sane.
    """
    t = text.strip()
    if not all_bold or size < 10.5:
        return 0
    if CHAPTER_RE.match(t):
        return 1
    if NUM3_RE.match(t):
        return 3
    if NUM2_RE.match(t):
        return 2
    if size >= 17:                       # unnumbered giant head, still not h1
        return 2
    if size >= 12.5:
        return 3
    if size >= 10.5 and len(t) <= 100:
        return 4
    return 0


def parse_pages(doc, page_range):
    """Yield ('heading', level, text, pageno) and ('body', 0, text, pageno)
    and ('code', 0, text, pageno) in reading order."""
    for pno in page_range:
        page = doc[pno]
        for block in page.get_text("dict")["blocks"]:
            if "lines" not in block:
                continue
            # bucket this block's lines
            raw = []
            for line in block["lines"]:
                info = line_info(line)
                if info is None:
                    continue
                text, size, all_bold = info
                y0 = line["bbox"][1]
                y1 = line["bbox"][3]
                if y0 < HEADER_Y or y1 > FOOTER_Y:
                    continue
                stripped = text.strip()
                if stripped in DROP_LINES or SEITE_RE.match(stripped):
                    continue
                if DOTLEADER_RE.search(stripped):        # TOC leader line
                    continue
                if round(size) == 12 and len(stripped) <= 3:   # stray glyph/digit
                    continue
                raw.append((text, size, all_bold, round(size)))

            if not raw:
                continue

            # size-8 run -> preformatted code block
            if all(r[3] == 8 for r in raw):
                code = clean_text("\n".join(r[0].rstrip() for r in raw)).strip("\n")
                if code.strip():
                    yield ("code", 0, code, pno + 1)
                continue

            # heading block: 1-2 lines, all heading-level, same level
            levels = [heading_level(*r[:3]) for r in raw]
            if raw and all(lv > 0 for lv in levels) and len(raw) <= 2:
                lvl = min(l for l in levels if l > 0)
                htext = clean_text(" ".join(r[0].strip() for r in raw)).strip()
                htext = re.sub(r"\s+", " ", htext)
                if htext:
                    yield ("heading", lvl, htext, pno + 1)
                continue

            # otherwise: body paragraph. If the first line is a heading and the
            # rest is prose, split them.
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
    assert join_hyphenated(["Kom-", "plexität ist"]) == "Komplexität ist"
    assert join_hyphenated(["Build-", "und Buy"]) == "Build- und Buy"
    assert join_hyphenated(["ein", "Satz"]) == "ein Satz"
    assert heading_level("2.3 Das FFQ-Modell", 15.0, True) == 2
    assert heading_level("2.3.1 Definition", 13.0, True) == 3
    assert heading_level("Kapitel 2: Foo", 18.0, True) == 1
    assert heading_level("Das Modell der drei Perspektiven", 15.0, True) == 3
    assert heading_level("normaler Fließtext", 10.0, False) == 0
    assert clean_text("Budget &amp; Zeit <!-- note -->x") == "Budget & Zeit x"
    assert sentence_split("Ein Satz. Noch einer. Und drei.", 3)  # doesn't crash
    print("selftest ok")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--pages", help="1-based inclusive range, e.g. 12-60")
    ap.add_argument("--skip", help="1-based inclusive range to drop, e.g. 2-10 (TOC)")
    ap.add_argument("--src", type=Path, default=SRC, help="source PDF")
    ap.add_argument("--out", type=Path, default=OUT_DIR, help="output directory")
    ap.add_argument("--selftest", action="store_true")
    args = ap.parse_args()

    if args.selftest:
        selftest()
        return

    doc = pymupdf.open(args.src)
    rng = parse_page_range(args.pages, doc.page_count)

    # --skip drops a contiguous 1-based page span (the front-matter TOC has no
    # dot-leaders in this manuscript, so it can't be filtered by content).
    if args.skip:
        a, b = (int(x) for x in args.skip.split("-"))
        skip = set(range(a - 1, b))
        rng = [p for p in rng if p not in skip]

    run_extraction(doc, args.out, "digitalentwurfslehre", "del-", rng, parse_pages)


if __name__ == "__main__":
    main()
