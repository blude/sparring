"""
Shared PDF -> markdown + RAG-chunk extraction helpers for the curriculum
corpus (data/curriculum/, gitignored).

Everything here is format-independent: it operates on a normalized item stream
of ("heading", level, text, page) / ("body", 0, text, page) /
("code", 0, text, page) tuples in reading order, produced by a per-PDF
parse_pages() function that each extractor script supplies itself.

Consumers:
  bin/extract_digitalentwurfslehre.py   (Kim Lauenroth manuscript, V1.0)
  bin/extract_ddp_handbook.py           (IREB DDP Foundation Level Handbuch)

The two source PDFs are structurally different (fonts, heading styling,
running header/footer, bullet glyphs), so each script keeps its own
line_info()/heading_level()/parse_pages() and calls run_extraction() with the
rest.

Needs:  pip install pymupdf tiktoken
"""
import html
import json
import re

import tiktoken

CHUNK_TARGET = 700     # soft token target per chunk
CHUNK_MAX = 950        # hard ceiling before a lone paragraph is sentence-split
HEADING_FLUSH_MIN = 300  # only break a chunk at a heading once it's this big
                         # (a manuscript with runs of tiny subheads would
                         #  otherwise produce dozens of 20-token chunks)

ENC = tiktoken.get_encoding("cl100k_base")
DOTLEADER_RE = re.compile(r"\.{4,}")
HTML_COMMENT_RE = re.compile(r"<!--.*?-->", re.DOTALL)
SENT_SPLIT_RE = re.compile(r"(?<=[.?!])\s+(?=[A-ZÄÖÜ])")


def ntok(s: str) -> int:
    return len(ENC.encode(s))


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


def coalesce_code(items):
    """Merge consecutive ('code', ...) items into one block. A manuscript's
    templates (e.g. a one-page Projekt Brief) are emitted by PyMuPDF as one
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


def build_chunks(items, id_prefix):
    """Greedy pack body paragraphs to ~CHUNK_TARGET tokens, never splitting a
    paragraph (except a lone > CHUNK_MAX one, on sentences). Chunks also break
    at level<=2 headings once they already hold >= HEADING_FLUSH_MIN tokens.

    id_prefix is stamped into each chunk's provisional id ("<prefix>0001"); the
    caller re-stamps final ids after the <30-token drop in run_extraction().
    """
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
            "id": f"{id_prefix}{len(chunks) + 1:04d}",
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
            # heading strings live only in section_path (which the caller
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


def parse_page_range(spec, page_count):
    """'A-B' (1-based inclusive) -> 0-based range; falsy spec -> whole doc."""
    if not spec:
        return range(page_count)
    a, b = (int(x) for x in spec.split("-"))
    return range(a - 1, b)


def run_extraction(doc, out_dir, stem, id_prefix, page_range, parse_pages_fn):
    """Shared tail: run parse_pages_fn over page_range, write <stem>.md and
    <stem>-chunks.jsonl into out_dir, print a stats block.

    doc is an already-open pymupdf document (the caller opens it, so it can
    also use doc.page_count to build page_range / apply a --skip span first).
    """
    md_path = out_dir / f"{stem}.md"
    jsonl_path = out_dir / f"{stem}-chunks.jsonl"

    items = coalesce_code(list(parse_pages_fn(doc, page_range)))
    md = build_markdown(items)
    chunks = build_chunks(items, id_prefix)

    out_dir.mkdir(parents=True, exist_ok=True)
    md_path.write_text(md, encoding="utf-8")
    # drop degenerate chunks (lone placeholder stubs like "Platzhalter, wird
    # noch gefüllt") — nothing to retrieve on — then renumber ids contiguously
    chunks = [c for c in chunks if c["token_count"] >= 30]
    for n, c in enumerate(chunks, 1):
        c["id"] = f"{id_prefix}{n:04d}"
    with jsonl_path.open("w", encoding="utf-8") as f:
        for c in chunks:
            f.write(json.dumps(c, ensure_ascii=False) + "\n")

    toks = [c["token_count"] for c in chunks]
    print(f"pages           {len(page_range)}")
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
