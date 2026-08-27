#!/usr/bin/env bash
set -euo pipefail

# Appends commits since the last "docs: update CHANGELOG through <hash>"
# commit to CHANGELOG.md (grouped under `## YYYY-MM-DD` headings, newest
# first, merging into today's heading if it's already the first one), then
# sets composer.json's version to 0.<N>.0 where N is the resulting count of
# date headings — one 0.1 per distinct day of work, recomputed from the file
# every run so version drift can't accumulate silently (see CHANGELOG.md
# 2026-08-27 entries for how it drifted before this script existed).
#
# Leaves the commit itself to the caller — this only edits the two files.
#
# Usage: bin/bump_changelog.sh

cd "$(dirname "$0")/.."

CHANGELOG=CHANGELOG.md
COMPOSER=composer.json

# Find the hash the last bump commit updated through, from its own message.
last_subject=$(git log -1 --grep='^docs: update CHANGELOG through ' --format='%s')
if [[ -z "$last_subject" ]]; then
    echo 'no prior "docs: update CHANGELOG through <hash>" commit found — nothing to diff from.' >&2
    exit 1
fi
since=$(sed -n 's/.*through \([0-9a-f]*\).*/\1/p' <<< "$last_subject")

log=$(git log --format='%h%x09%ad%x09%s' --date=short "$since"..HEAD)
if [[ -z "$log" ]]; then
    echo "no commits since $since — nothing to do." >&2
    exit 1
fi

# Group commits by date, preserving git log's newest-first order both across
# and within groups (matches the file's existing convention).
declare -A date_lines
date_order=()
while IFS=$'\t' read -r hash date subject; do
    if [[ -z "${date_lines[$date]+x}" ]]; then
        date_order+=("$date")
        date_lines[$date]=""
    fi
    date_lines[$date]+="- \`$hash\` $subject"$'\n'
done <<< "$log"

# Insert `block` right after the first line in the file matching `marker`
# (exact match) and the blank line that follows it.
insert_after() {
    # awk's -v mangles multi-line values on some implementations (macOS's
    # BSD awk included) — pass marker/block through the environment instead.
    MARKER=$1 BLOCK=$2 awk '
        state == 0 && $0 == ENVIRON["MARKER"] { print; state = 1; next }
        state == 1 { print; printf "%s", ENVIRON["BLOCK"]; state = 2; next }
        { print }
    ' "$CHANGELOG" > "$CHANGELOG.tmp"
    mv "$CHANGELOG.tmp" "$CHANGELOG"
}

for date in "${date_order[@]}"; do
    entries="${date_lines[$date]}"
    heading="## $date"
    if grep -qxF "$heading" "$CHANGELOG"; then
        insert_after "$heading" "$entries"
    else
        insert_after 'Generated from git history. Grouped by commit date, newest first.' "$heading"$'\n\n'"$entries"$'\n'
    fi
done

heading_count=$(grep -cE '^## [0-9]{4}-[0-9]{2}-[0-9]{2}$' "$CHANGELOG")
new_version="0.${heading_count}.0"

awk -v v="$new_version" '{ sub(/"version": *"[^"]*"/, "\"version\": \"" v "\""); print }' "$COMPOSER" > "$COMPOSER.tmp"
mv "$COMPOSER.tmp" "$COMPOSER"

head_hash=$(git rev-parse --short HEAD)
echo "CHANGELOG.md updated through $head_hash, version set to $new_version ($heading_count date headings)."
echo "Review the diff, then commit as: docs: update CHANGELOG through $head_hash"
