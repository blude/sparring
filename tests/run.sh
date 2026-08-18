#!/bin/sh
# Runs every smoke test (PHP + Node). Stops on first failure.
# Each test below runs in its own process, deliberately — several smoke
# scripts declare the same class names (e.g. LlmClientInterface), so
# running them in one shared PHP process instead would fatal on redeclare.
# Run: tests/run.sh  or  sh tests/run.sh
set -e
cd "$(dirname "$0")/.."

GREEN_CHECK="$(printf '\033[32m\xe2\x9c\x93\033[0m')" # ✓, green

total=$(ls tests/smoke_*.php tests/smoke_*.js | wc -l | tr -d ' ')
n=0

run_test() {
    n=$((n + 1))
    if out=$("$1" "$2" 2>&1); then
        printf '[%d/%d] %s\n' "$n" "$total" "$(printf '%s' "$out" | sed "s/ok\$/$GREEN_CHECK/")"
    else
        printf '[%d/%d] %s\n' "$n" "$total" "$out"
        exit 1
    fi
}

for f in tests/smoke_*.php; do
    run_test php "$f"
done
for f in tests/smoke_*.js; do
    run_test node "$f"
done

echo "all: ok $GREEN_CHECK"
