#!/usr/bin/env bash
set -euo pipefail

# Rebuilds spec/*.adoc into public/spec/*.html. No build step at deploy
# time (SC-03/C-04) — the HTML is generated here and committed, same as
# any other file in public/.
#
# Usage: bin/build_spec.sh

cd "$(dirname "$0")/.."

if ! command -v asciidoctor >/dev/null; then
    echo "asciidoctor not found — install with: gem install asciidoctor" >&2
    exit 1
fi

# book.adoc is the PDF-only combined edition (bin/build_spec_pdf.sh) — it
# isn't meant to render as its own HTML page here.
shopt -s extglob
asciidoctor -D public/spec -a docinfo=shared-footer -a nofooter -a linkcss -a copycss spec/!(book).adoc
echo "Built $(ls public/spec/*.html | wc -l | tr -d ' ') files into public/spec/."
