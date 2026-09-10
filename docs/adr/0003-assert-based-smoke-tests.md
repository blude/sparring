# 3. Assert-based smoke tests, no test framework

- **Status:** Accepted
- **Date:** Project inception (back-filled 2026-09-10)
- **References:** `CLAUDE.md` "Test"; `tests/run.sh`; `tests/smoke_*.php`, `tests/smoke_*.js`

## Context

The codebase has no build step and one runtime dependency. Adding PHPUnit
plus a JS test runner would be the two largest dev dependencies in the
project, for a POC whose risky logic is a handful of pure functions
(gate/moderation classification, retrieval scoring, session-state maths,
client-side diff and audio logic).

## Decision

Test with plain assert-based smoke scripts and no framework. `tests/run.sh`
runs them all — PHP first, then Node — each in its own process, stopping
on the first failure. PHP checks run via `php tests/smoke_*.php`; JS checks
run under Node purely as a runner (`node tests/smoke_*.js`), not as a
project dependency. `php -l` is the only lint.

Conversational and pedagogical quality of `prompts/sparring.md` is
explicitly out of scope for this suite — it never calls a real LLM. That
is covered separately by `evals/sparring/` (real Anthropic calls, its own
README).

## Consequences

- No fixtures framework, no mocking library, no coverage tool. Tests that
  need data use small committed fixtures (`tests/fixtures/curriculum/` is
  verbatim excerpts of the gitignored real corpus, kept small so the suite
  is reproducible on a fresh checkout).
- Each new `bin/` or `src/` unit with non-trivial logic gets one smoke
  script mirroring an existing one.
- Anything requiring a real browser DOM or a real network call is not
  covered here by design.
