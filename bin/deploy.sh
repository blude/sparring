#!/usr/bin/env bash
set -euo pipefail

# Deploys this repo to the EasyEngine prod site over rsync+SSH, then runs
# composer install inside the site's docker container (composer lives in
# the container, not the host).
#
# Destination is the site's app root (htdocs/), NOT htdocs/public — this
# repo's own public/ subfolder lands at htdocs/public/ that way, keeping
# config.php/src/data/prompts outside the served docroot per README.
#
# Host/path are environment-specific, not this repo's business to hardcode
# (repo may go public) — set them in bin/deploy.env, git-ignored, same
# pattern as the project's own .env. See bin/deploy.env.example.
#
# Usage: bin/deploy.sh [--dry-run]

cd "$(dirname "$0")/.."

if [[ ! -f bin/deploy.env ]]; then
    echo "missing bin/deploy.env — copy bin/deploy.env.example and fill in your host/path" >&2
    exit 1
fi
source bin/deploy.env

# -rlptDz, not -az: skip -o/-g (owner/group). Preserving *local* mac
# ownership across the SSH boundary lands files owned by a UID meaningless
# inside the remote container, which is why the container's composer
# couldn't create vendor/ on the first run.
RSYNC_FLAGS=(-rlptDz --delete --exclude=.git --exclude=.claude --exclude-from=.gitignore)
DRY_RUN=false
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=true
    RSYNC_FLAGS+=(--dry-run -v)
    echo "--- dry run: nothing will be copied, deleted, or installed ---"
fi

rsync "${RSYNC_FLAGS[@]}" ./ "$REMOTE_HOST:$REMOTE_PATH"

if [[ "$DRY_RUN" == true ]]; then
    exit 0
fi

# rsync's own ownership is whatever the SSH user (root) defaults to — not
# necessarily the site's actual runtime user, and not enough by itself to
# fix files a previous deploy already left owned by the wrong UID. Force it
# explicitly to match the site (its app/ parent dir is SITE_OWNER already).
ssh "$REMOTE_HOST" "chown -R $SITE_OWNER '$REMOTE_PATH'"

# `ee shell` lands directly in htdocs/ already — no cd needed.
ssh "$REMOTE_HOST" "ee shell $SITE_NAME --command='composer install --no-dev --no-interaction'"

cat <<EOF
Deployed.
First deploy on a fresh site only:
  - create htdocs/.env from .env.example with ANTHROPIC_API_KEY set
    (not EasyEngine's site-root .env — different file)
  - seed data/store.db (host PHP may be older than 8.2+, run it in-container):
      ssh $REMOTE_HOST "ee shell $SITE_NAME --command='php bin/import_pilot.php'"
  - confirm data/ is writable by the container's PHP-FPM user
  - data/curriculum/ is git-ignored, not synced by this script — whenever
    the corpus changes (not every deploy), run bin/deploy_curriculum.sh
EOF
