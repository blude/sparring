#!/bin/sh
# Runs every smoke test (PHP + Node). Stops on first failure.
# Each `php "$f"` below is its own process, deliberately — several smoke
# scripts declare the same class names (e.g. LlmClientInterface), so
# running them in one shared PHP process instead would fatal on redeclare.
# Run: tests/run.sh  or  sh tests/run.sh
set -e
cd "$(dirname "$0")/.."

for f in tests/smoke_*.php; do
    php "$f"
done
for f in tests/smoke_*.js; do
    node "$f"
done

echo "OK: all smoke tests passed"
