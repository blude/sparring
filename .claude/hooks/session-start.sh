#!/bin/bash
# SessionStart hook for Claude Code on the web: a fresh cloud container has
# no vendor/, so tests/run.sh fatals on the missing autoloader, and
# core.hooksPath is unset, so .githooks/ never runs. Local sessions skip
# this: a local checkout is already set up per README.
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "$CLAUDE_PROJECT_DIR"
# Instant, so it runs before going async: git hooks are live even for a
# commit made in the first seconds of the session.
git config core.hooksPath .githooks

# Everything below runs in the background while the session starts.
# Race: tests/run.sh fails on the missing vendor/autoload.php until
# composer install finishes (~15s on a cold container, near no-op on resume
# since the container snapshot caches vendor/).
echo '{"async": true, "asyncTimeout": 300000}'
composer install --no-interaction --no-progress --quiet
