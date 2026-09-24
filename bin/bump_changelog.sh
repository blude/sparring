#!/usr/bin/env bash
set -euo pipefail

# Appends commits since the last "docs: update CHANGELOG through <hash>"
# commit to CHANGELOG.md (grouped under `## YYYY-MM-DD` headings, newest
# first, merging into today's heading if it's already the first one), then
# sets composer.json's version to <MAJOR>.<N>.0 where N is the count of date
# headings dated strictly after RELEASE_DATE — one 0.1 per distinct day of
# work since the current release. Recomputed from the file every run so
# version drift can't accumulate silently (see CHANGELOG.md 2026-08-27
# entries for how it drifted before this script existed). MAJOR and
# RELEASE_DATE are fixed below and updated by hand at each release milestone,
# which resets N to 0 (see the `v*` git tags).
#
# Leaves the commit itself to the caller — this only edits CHANGELOG.md,
# composer.json and composer.lock.
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

# Oldest date first: each new `## date` heading is inserted directly below
# the intro line, so processing oldest→newest leaves the newest on top and
# keeps the file's "newest first" order even when a run spans several days.
for (( i = ${#date_order[@]} - 1; i >= 0; i-- )); do
    date="${date_order[$i]}"
    entries="${date_lines[$date]}"
    heading="## $date"
    if grep -qxF "$heading" "$CHANGELOG"; then
        insert_after "$heading" "$entries"
    else
        insert_after 'Generated from git history. Grouped by commit date, newest first.' "$heading"$'\n\n'"$entries"$'\n'
    fi
done

# Both bumped by hand at each release milestone; the `v*` git tags are the
# record. RELEASE_DATE is the day MAJOR shipped — the minor counts CHANGELOG
# date headings *after* it, so it resets to 0 on a new release.
MAJOR=1
RELEASE_DATE=2026-09-04
# ISO dates sort lexically, so a plain string `>` compares them correctly.
# One awk pass keeps this BSD-awk-safe and can't trip pipefail on no match.
heading_count=$(awk -v r="$RELEASE_DATE" '
    /^## [0-9]{4}-[0-9]{2}-[0-9]{2}$/ { if ($2 > r) n++ }
    END { print n + 0 }
' "$CHANGELOG")
new_version="${MAJOR}.${heading_count}.0"

awk -v v="$new_version" '{ sub(/"version": *"[^"]*"/, "\"version\": \"" v "\""); print }' "$COMPOSER" > "$COMPOSER.tmp"
mv "$COMPOSER.tmp" "$COMPOSER"
# Composer hashes "version" into composer.lock's content-hash, so the edit
# above leaves the lock stale ("lock file is not up to date"). --lock only
# refreshes that hash; no package changes.
composer update --lock --no-interaction --quiet

head_hash=$(git rev-parse --short HEAD)
echo "CHANGELOG.md updated through $head_hash, version set to $new_version ($heading_count date headings)."
echo "Review the diff, then commit as: docs: update CHANGELOG through $head_hash"
