#!/bin/bash
# SessionStart hook for Claude Code on the web: a fresh cloud container has
# no vendor/, so tests/run.sh fatals on the missing autoloader, and
# core.hooksPath is unset, so .githooks/ never runs. `composer install`
# fixes both (its post-install-cmd wires core.hooksPath). Local sessions
# skip this: a local checkout is already set up per README.
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "$CLAUDE_PROJECT_DIR"
# install, not a lockfile-strict CI install: the container snapshot caches
# vendor/, so re-runs on resume are near no-ops.
composer install --no-interaction --no-progress --quiet
git config core.hooksPath .githooks
