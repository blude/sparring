#!/bin/sh
# Runs every smoke test (PHP + Node). Stops on first failure.
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
