#!/usr/bin/env bash
set -euo pipefail

# Builds the combined book edition of the spec into
# public/spec/sparring-spec.pdf. Separate from build_spec.sh (which builds the
# canonical HTML and is fast) because this one shells out to a headless
# browser once per Mermaid diagram and takes ~30s.
#
# Why the Mermaid pre-render below instead of `asciidoctor -r
# asciidoctor-diagram`: Homebrew's asciidoctor pins GEM_HOME to a sealed,
# versioned dir (Cellar/asciidoctor/<ver>/libexec) that `brew upgrade` wipes,
# so plugin gems don't stick. Rather than fight that, this script turns each
# `[source,mermaid]` block into a PNG with mermaid-cli (via npx, no install)
# and rewrites it to an `image::` before asciidoctor-pdf ever runs. See
# docs/adr/0015-spec-pdf-book-target.md.
#
# Usage: bin/build_spec_pdf.sh

cd "$(dirname "$0")/.."

if ! command -v asciidoctor-pdf >/dev/null; then
    echo "asciidoctor-pdf not found — install with: brew install asciidoctor" >&2
    exit 1
fi
if ! command -v npx >/dev/null; then
    echo "npx not found — install Node.js (mermaid-cli is fetched via npx, not committed)" >&2
    exit 1
fi

out=public/spec/sparring-spec.pdf
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

# Work on copies: the source .adoc files are never touched. index.adoc is the
# HTML edition's landing page (an xref list to the others) — book.adoc has its
# own front matter and doesn't include it, so drop it here too.
cp spec/*.adoc "$work"/
rm -f "$work"/index.adoc

# Map each source file to a short anchor prefix. Requirement IDs (E-01.1,
# G-01, ...) repeat across the L3 files, so merging the files into one book
# makes them collide; prefixing every anchor per file keeps them unique and
# lets the cross-file `xref:other.adoc#ID[]` links resolve as local links.
# Only the link target is rewritten — every `<<>>`/`xref:` in these files
# already carries an explicit label — so rendered text is unchanged.
prefix_for() {
    case "$1" in
        L1-*)       echo L1 ;;
        L2-*)       echo L2 ;;
        L3-SE-01-*) echo SE-01 ;;
        L3-SE-02-*) echo SE-02 ;;
        L3-SE-03-*) echo SE-03 ;;
        L3-SE-04-*) echo SE-04 ;;
        LX-*)       echo LX ;;
        glossary*)  echo GL ;;
    esac
}

for f in "$work"/*.adoc; do
    b="$(basename "$f")"
    [ "$b" = book.adoc ] && continue
    p="$(prefix_for "$b")"
    # Per file: give the chapter a stable id (chap-<prefix>), drop the
    # standalone header on lines 2-9 (book.adoc supplies its own), strip the
    # HTML-only breadcrumb line, and prefix every anchor definition and
    # same-file `<<ID,...>>` reference.
    P="$p" perl -i -pe '
        my $p = $ENV{P};
        if ($. == 1) { $_ = "[#chap-$p]\n" . $_ . "\n"; }
        $_ = "" if $. >= 2 && $. <= 9;
        s{^xref:index\.adoc\[Index\].*\n}{};
        s/\[\[([\w.-]+)\]\]/[[$p-$1]]/g;
        s/<<([\w.-]+)([,>])/<<$p-$1$2/g;
    ' "$f"
done

# Across all files: turn the cross-file references into local links now that
# every target lives in the same document. `#ID` goes to the prefixed anchor;
# a bare `file.adoc[...]` (whole-document link) goes to that file's chapter id.
perl -i -pe '
    my %pfx = (
        "L1-solution-design-concept"   => "L1",
        "L2-system-design-concept"     => "L2",
        "L3-SE-01-input-client"        => "SE-01",
        "L3-SE-02-display-client"      => "SE-02",
        "L3-SE-03-backend-service"     => "SE-03",
        "L3-SE-04-system-prompt"       => "SE-04",
        "LX-system-realization-concept" => "LX",
        "glossary"                     => "GL",
    );
    for my $f (keys %pfx) {
        my $q = quotemeta $f;
        my $pre = $pfx{$f};
        s/xref:$q\.adoc#([\w.-]+)/"xref:" . $pre . "-" . $1/ge;
        s/xref:$q\.adoc\[/"xref:chap-" . $pre . "["/ge;
    }
' "$work"/*.adoc

# Pull every `[source,mermaid]` block out to a .mmd sidecar and leave an
# `image::diag-N.png[]` in its place. One shared counter across all files so
# the PNG names don't collide.
awk -v dir="$work" '
    FNR == 1 { out = FILENAME ".new"; body = 0; pending = 0 }
    pending {
        pending = 0
        if ($0 ~ /^-{4,}[[:space:]]*$/) { body = 1; mmd = dir "/diag-" (++n) ".mmd"; printf "" > mmd; next }
        print "[source,mermaid]" > out          # not actually a diagram, put it back
    }
    !body && $0 == "[source,mermaid]" { pending = 1; next }
    body && $0 ~ /^-{4,}[[:space:]]*$/ { body = 0; close(mmd); print "image::diag-" n ".png[]" > out; next }
    body { print > mmd; next }
    { print > out }
' "$work"/*.adoc

for mmd in "$work"/diag-*.mmd; do
    [ -e "$mmd" ] || break
    npx --yes -p @mermaid-js/mermaid-cli@11 mmdc -i "$mmd" -o "${mmd%.mmd}.png" -s 2 >/dev/null
done

for new in "$work"/*.adoc.new; do
    mv "$new" "${new%.new}"
done

asciidoctor-pdf "$work/book.adoc" -o "$out"
echo "Built $out ($(du -h "$out" | cut -f1), $(ls "$work"/diag-*.png 2>/dev/null | wc -l | tr -d ' ') diagrams)."
