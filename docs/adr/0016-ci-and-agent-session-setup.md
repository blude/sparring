# 16. GitHub Actions CI and a committed Claude Code session setup

- **Status:** Accepted
- **Date:** 2026-09-24
- **References:** `.github/workflows/ci.yml`; `.claude/settings.json`;
  `.claude/hooks/session-start.sh`; `.githooks/pre-commit`; ADR
  [0003](0003-assert-based-smoke-tests.md) (the tests CI runs); ADR
  [0001](0001-vanilla-php-sqlite-no-framework.md) (no build step)

## Context

Every check the project relies on (`tests/run.sh`, `php -l`, the
Conventional Commits hook, the `prompts/CHANGELOG.md` rule, committing a
freshly built `public/spec/`) ran only if whoever committed remembered to
run it. Much of the work is done by coding agents, several in fresh
Claude Code on the web containers. There, `vendor/` is missing, so
`tests/run.sh` fails on the autoloader, and `core.hooksPath` is unset, so
the commit-msg hook never runs. The prompt-changelog rule existed only as
prose in `CLAUDE.md`.

## Decision

- **CI:** one GitHub Actions workflow runs `php -l` on every tracked PHP
  file and `tests/run.sh` (PHP 8.2, the `composer.json` floor; Node 22).
  It also rebuilds `spec/` with a pinned Asciidoctor (2.0.26, the version
  that built the committed HTML) and fails if `public/spec/` changes. On
  PRs it replays `.githooks/commit-msg` over each commit and checks the
  prompt-changelog rule over the whole diff. No real LLM calls:
  `evals/sparring/` stays manual.
- **Git hook:** `.githooks/pre-commit` enforces the prompt-changelog rule
  locally, same POSIX-sh/no-deps shape as `commit-msg`.
- **Agent sessions:** `.claude/settings.json` is committed with a
  SessionStart hook that runs `composer install` and wires
  `core.hooksPath`, but only when `CLAUDE_CODE_REMOTE=true`; local
  checkouts are set up per README. It also has a permission allowlist for
  the test and lint commands and a denylist for `bin/deploy*.sh` and the
  destructive `bin/` data scripts.

## Consequences

- CI is tooling around the repo, not part of what gets deployed. Nothing
  is added to `composer.json` or served from `public/`, so ADR 0001's
  no-build-step rule still holds.
- A spec edit must now ship with its rebuilt `public/spec/` HTML, or CI
  goes red. Upgrading Asciidoctor means updating the pin and committing
  the rebuilt output in the same change.
- A whitespace-only prompt fix needs `git commit --no-verify` locally and
  would still be flagged by CI; the fix is a one-line changelog entry.
- The permission denylist only guards agent tool calls. It isn't a
  security boundary: deploy credentials in `bin/deploy.env` stay the real
  gate.
