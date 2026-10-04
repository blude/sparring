#!/bin/bash
# SessionStart hook for Claude Code on the web: a fresh cloud container has
# no vendor/, so tests/run.sh fatals on the missing autoloader, and
# core.hooksPath is unset, so .githooks/ never runs. Local sessions skip
# this: a local checkout is already set up per docs/getting-started.md.
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "$CLAUDE_PROJECT_DIR"
git config core.hooksPath .githooks

# Runs async (`"async": true` on this hook in .claude/settings.json), so the
# session starts without waiting. Race: tests/run.sh fails on the missing
# vendor/autoload.php until this finishes (~15s on a cold container, near
# no-op on resume since the container snapshot caches vendor/). Claude Code
# doesn't enforce a timeout on async hooks, hence the explicit one.
timeout 300 composer install --no-interaction --no-progress --quiet
