#!/usr/bin/env python3
"""
Extract "Digitalentwurfslehre" (Kim Lauenroth, manuscript V1.0, 1216 pp.)
from PDF to a clean markdown file + a ~700-token JSONL chunk set for RAG.

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
  - The table of contents (pp. ~2-10) is pure dot-leader lines ("...."); any
    line with 4+ consecutive dots is a TOC artifact and dropped everywhere.
  - The author left HTML-comment placeholders ("<!-- ... -->") in the text;
    stripped. Text is HTML-unescaped ("&amp;" -> "&").

Needs:  pip install pymupdf tiktoken

Usage:
  python bin/extract_digitalentwurfslehre.py                 # full book
  python bin/extract_digitalentwurfslehre.py --pages 12-60   # subset, to eyeball
  python bin/extract_digitalentwurfslehre.py --src PATH --out DIR
"""
import argparse
import html
import json
import re
from pathlib import Path

import pymupdf
import tiktoken

# Paths default relative to the repo root (this file lives in <repo>/bin/), so
# the script is runnable on any checkout. data/curriculum/ is gitignored — the
# source PDF must be placed there (or passed with --src).
REPO = Path(__file__).resolve().parent.parent
SRC = REPO / "data/curriculum/digitalentwurfslehre-raw.pdf"
OUT_DIR = REPO / "data/curriculum/digitalentwurfslehre-content"

HEADER_Y = 45.0        # anything with top above this is the running header
FOOTER_Y = 790.0       # anything with bottom below this is the running footer
CHUNK_TARGET = 700     # soft token target per chunk
CHUNK_MAX = 950        # hard ceiling before a lone paragraph is sentence-split
HEADING_FLUSH_MIN = 300  # only break a chunk at a heading once it's this big
                         # (the manuscript has runs of tiny 15pt subheads that
                         #  would otherwise produce dozens of 20-token chunks)

ENC = tiktoken.get_encoding("cl100k_base")
DROP_LINES = {"Digitalentwurfslehre — Kim Lauenroth"}
SEITE_RE = re.compile(r"^Seite \d+\s*$")
DOTLEADER_RE = re.compile(r"\.{4,}")
NUM3_RE = re.compile(r"^\d+\.\d+\.\d+")
NUM2_RE = re.compile(r"^\d+\.\d+(?!\.)")
CHAPTER_RE = re.compile(r"^(Kapitel|Teil|Anhang)\b")
HTML_COMMENT_RE = re.compile(r"<!--.*?-->", re.DOTALL)
SENT_SPLIT_RE = re.compile(r"(?<=[.?!])\s+(?=[A-ZÄÖÜ])")


def ntok(s: str) -> int:
    return len(ENC.encode(s))


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


def clean_text(s: str) -> str:
    # unescape FIRST so "&lt;!--" becomes "<!--" and gets stripped below
    s = html.unescape(s)
    s = HTML_COMMENT_RE.sub("", s)
    # the manuscript also has *unpaired* halves ("<!--" on a line by itself,
    # "-->" three blocks later) that the paired regex above can't catch
    s = s.replace("<!--", "").replace("-->", "")
    return s


def join_hyphenated(lines):
    """Join a paragraph's physical lines, undoing end-of-line hyphenation.

    German keeps many real hyphens, including suspended ones ("Build- und
    Buy-Entscheidung"). Merge when the break looks like a split word: prev
    ends '<letter>-' and next starts lowercase -- UNLESS the next token is a
    conjunction/particle ("und", "oder", ...), which marks a suspended hyphen
    that must stay. Otherwise keep the hyphen + a space.
    """
    SUSPENDED = {"und", "oder", "bzw", "bzw.", "sowie", "wie", "auch",
                 "noch", "aber", "als", "bis"}
    out = ""
    for i, ln in enumerate(lines):
        ln = ln.strip()
        if i == 0:
            out = ln
            continue
        first_word = ln.split(" ", 1)[0].strip(",.;:")
        if (out.endswith("-") and len(out) >= 2 and out[-2].isalpha()
                and ln[:1].islower() and first_word.lower() not in SUSPENDED):
            out = out[:-1] + ln
        else:
            out = out + " " + ln
    return out.strip()


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


def coalesce_code(items):
    """Merge consecutive ('code', ...) items into one block. The manuscript's
    templates (e.g. the one-page Projekt Brief) are emitted by PyMuPDF as one
    block per line, which would otherwise become a dozen tiny ``` fences."""
    out = []
    for it in items:
        if it[0] == "code" and out and out[-1][0] == "code":
            prev = out[-1]
            out[-1] = ("code", 0, prev[2] + "\n" + it[2], prev[3])
        else:
            out.append(it)
    return out


def build_markdown(items):
    lines = []
    cur_page = None
    for kind, level, text, page in items:
        if page != cur_page:
            lines.append(f"\n<!-- page {page} -->\n")
            cur_page = page
        if kind == "heading":
            lines.append(f"\n{'#' * level} {text}\n")
        elif kind == "code":
            lines.append(f"\n```\n{text}\n```\n")
        else:
            lines.append(text + "\n")
    md = "\n".join(lines)
    md = re.sub(r"\n{4,}", "\n\n\n", md)
    return md.strip() + "\n"


def sentence_split(text, limit):
    """Break one oversized paragraph into <=limit-token pieces on sentence ends."""
    sents = SENT_SPLIT_RE.split(text)
    pieces, buf = [], ""
    for s in sents:
        cand = (buf + " " + s).strip()
        if buf and ntok(cand) > limit:
            pieces.append(buf)
            buf = s
        else:
            buf = cand
    if buf:
        pieces.append(buf)
    return pieces


def build_chunks(items):
    """Greedy pack body paragraphs to ~CHUNK_TARGET tokens, never splitting a
    paragraph (except a lone > CHUNK_MAX one, on sentences). Chunks also break
    at level<=2 headings once they already hold >= HEADING_FLUSH_MIN tokens."""
    stack = {}          # heading level -> current text
    chunks = []
    buf = []            # list of (text, page)
    buf_tokens = 0
    buf_path = []

    def section_path():
        return [stack[k] for k in sorted(stack) if stack[k]]

    def flush():
        nonlocal buf, buf_tokens
        if not buf:
            return
        # Join paragraphs with a blank line, EXCEPT where a paragraph was only
        # split because a page break landed mid-sentence: previous piece ends
        # without sentence-final punctuation and the next starts lowercase ->
        # stitch with a single space so the chunk reads as one paragraph.
        parts = [buf[0][0]]
        for t, _ in buf[1:]:
            prev = parts[-1].rstrip()
            if prev and prev[-1] not in ".?!:;»\"" and t[:1].islower():
                parts[-1] = prev + " " + t
            else:
                parts.append(t)
        text = "\n\n".join(parts)
        pages = [p for _, p in buf]
        chunks.append({
            "id": f"del-{len(chunks) + 1:04d}",
            "text": text,
            "page_start": min(pages),
            "page_end": max(pages),
            "section_path": " > ".join(buf_path),
            "token_count": ntok(text),
        })
        buf, buf_tokens = [], 0

    for kind, level, text, page in items:
        if kind == "heading":
            if level <= 2 and buf_tokens >= HEADING_FLUSH_MIN:
                flush()
            stack[level] = text
            for deeper in [k for k in stack if k > level]:
                stack.pop(deeper)
            # Emit the heading line INTO the chunk body too. Without this the
            # 3000+ heading strings live only in section_path (which the caller
            # may not index) and, for level 3/4 headings that never trigger a
            # flush, the chunk they fall inside keeps the section_path captured
            # at its start — stale. A visible "### ..." line fixes retrieval
            # both ways for ~10 tokens.
            hline = f"{'#' * level} {text}"
            if not buf:
                buf_path = section_path()
            buf.append((hline, page))
            buf_tokens += ntok(hline)
            continue

        # code blocks travel with prose as fenced text
        payload = f"```\n{text}\n```" if kind == "code" else text
        ptoks = ntok(payload)

        if ptoks > CHUNK_MAX and kind == "body":
            flush()
            for piece in sentence_split(payload, CHUNK_TARGET):
                buf = [(piece, page)]
                buf_tokens = ntok(piece)
                buf_path = section_path()
                flush()
            continue

        if buf and buf_tokens + ptoks > CHUNK_TARGET:
            flush()
        if not buf:
            buf_path = section_path()
        buf.append((payload, page))
        buf_tokens += ptoks

    flush()
    return chunks


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
    ap.add_argument("--src", type=Path, default=SRC, help="source PDF")
    ap.add_argument("--out", type=Path, default=OUT_DIR, help="output directory")
    ap.add_argument("--selftest", action="store_true")
    args = ap.parse_args()

    if args.selftest:
        selftest()
        return

    md_path = args.out / "digitalentwurfslehre.md"
    jsonl_path = args.out / "digitalentwurfslehre-chunks.jsonl"
    doc = pymupdf.open(args.src)
    if args.pages:
        a, b = (int(x) for x in args.pages.split("-"))
        rng = range(a - 1, b)
    else:
        rng = range(doc.page_count)

    items = coalesce_code(list(parse_pages(doc, rng)))
    md = build_markdown(items)
    chunks = build_chunks(items)

    args.out.mkdir(parents=True, exist_ok=True)
    md_path.write_text(md, encoding="utf-8")
    # drop degenerate chunks (lone placeholder stubs like "Platzhalter, wird
    # noch gefüllt" in the unfinished Anhang) — nothing to retrieve on
    chunks = [c for c in chunks if c["token_count"] >= 30]
    for n, c in enumerate(chunks, 1):
        c["id"] = f"del-{n:04d}"
    with jsonl_path.open("w", encoding="utf-8") as f:
        for c in chunks:
            f.write(json.dumps(c, ensure_ascii=False) + "\n")

    toks = [c["token_count"] for c in chunks]
    print(f"pages           {len(rng)}")
    print(f"items           {len(items)}  "
          f"(headings {sum(1 for i in items if i[0] == 'heading')}, "
          f"body {sum(1 for i in items if i[0] == 'body')}, "
          f"code {sum(1 for i in items if i[0] == 'code')})")
    print(f"markdown        {md_path}  ({len(md):,} chars)")
    print(f"chunks          {len(chunks)} -> {jsonl_path}")
    if toks:
        toks_sorted = sorted(toks)
        print(f"tokens/chunk    min {min(toks)}  "
              f"median {toks_sorted[len(toks) // 2]}  "
              f"mean {sum(toks) // len(toks)}  max {max(toks)}")
        print(f"                >{CHUNK_MAX}: {sum(1 for t in toks if t > CHUNK_MAX)}, "
              f"<150: {sum(1 for t in toks if t < 150)}")


if __name__ == "__main__":
    main()
