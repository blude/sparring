#!/usr/bin/env bash
set -euo pipefail

# Syncs data/curriculum/ (git-ignored, not touched by bin/deploy.sh) to the
# remote site and re-imports it. Run whenever the corpus changes — not on
# every deploy.
#
# Ownership: rsync leaves files owned by the SSH user (root), not the site's
# runtime user — chown after copying or import_curriculum.php can't read the
# files it just received (see prompts/CHANGELOG.md / this script's history
# for the permission-denied failure that motivated this).
#
# Usage: bin/deploy_curriculum.sh [--dry-run]

cd "$(dirname "$0")/.."

if [[ ! -f bin/deploy.env ]]; then
    echo "missing bin/deploy.env — copy bin/deploy.env.example and fill in your host/path" >&2
    exit 1
fi
source bin/deploy.env

RSYNC_FLAGS=(-rlptDz --delete --exclude=.git)
DRY_RUN=false
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=true
    RSYNC_FLAGS+=(--dry-run -v)
    echo "--- dry run: nothing will be copied, chowned, or imported ---"
fi

rsync "${RSYNC_FLAGS[@]}" data/curriculum/ "$REMOTE_HOST:${REMOTE_PATH}data/curriculum/"

if [[ "$DRY_RUN" == true ]]; then
    exit 0
fi

ssh "$REMOTE_HOST" "chown -R $SITE_OWNER '${REMOTE_PATH}data/curriculum'"
ssh "$REMOTE_HOST" "ee shell $SITE_NAME --command='php bin/import_curriculum.php'"

echo "Curriculum synced and imported."
