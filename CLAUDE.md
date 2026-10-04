# CLAUDE.md - Sparring Live

Thesis exhibition POC: visitor argues with an AI on their phone (SE-01), the
exchange projects on a wall (SE-02), one backend service (SE-03). See
`docs/getting-started.md` for setup/run, `docs/operations.md` for
export/DB/deploy, `spec/` for the design.

## Stack
- PHP 8.2+, vanilla JS, SQLite (`data/store.db`, WAL mode). No build step,
  no npm, no framework, no bundler — files served directly.
- Run: `composer install && export ANTHROPIC_API_KEY=... && php -S localhost:8080 -t public public/index.php`
  (the trailing `public/index.php` is the router script — pretty URLs like
  `/input` need it, real files still serve directly). Also runs under
  Laravel Valet (`valet park`/`link`), which routes the same way natively.
  `LLM_PROVIDER=fake` runs it offline with no key (canned `(fake)` replies,
  markers for the failure paths): use it to check UI or API changes end to
  end without real calls. See `docs/getting-started.md` "LLM providers".
- Test: assert-based smoke scripts, no framework. `tests/run.sh` runs all of
  them (PHP then Node, each its own process — see the script's own comment on
  why), stops on first failure. Individually — PHP needs
  `php -d zend.assertions=1 tests/<file>` (production php.ini compiles
  `assert()` out; each PHP script refuses to run without it). JS: `node
  tests/smoke_<name>.js` (Node only as a runner, not a project dependency).
  Each `tests/smoke_*` file's header says what it covers; new ones are
  picked up by `tests/run.sh` automatically.
- Visual changes (CSS, markup, client JS): run `php bin/screenshots.php`
  and look at the PNGs in `screenshots/` before calling it done. Pages in
  seeded states, fake LLM, throwaway DB; review aid, not a test (ADR 0018).
  CI attaches the same set to every PR.
- No linter configured — check changed files with `php -l <file>`.
- CI (`.github/workflows/ci.yml`, ADR 0016) re-runs `php -l` on every PHP
  file, `tests/run.sh`, a `public/spec/` drift check (rebuilt with
  Asciidoctor 2.0.26, must match what's committed), and, on PRs, the two
  git hooks below over every commit. Keep it green before pushing.
- Claude Code on the web: `.claude/hooks/session-start.sh` wires
  `core.hooksPath` at session start, then runs `composer install` in the
  background (async), so tests and hooks work in a fresh container. If
  `tests/run.sh` fails on a missing `vendor/autoload.php` in the first
  seconds of a session, the install is still running: wait and re-run,
  don't install by hand.
- `tests/run.sh` is plumbing only — it never calls a real LLM. Conversational/
  pedagogical quality of `prompts/sparring.md` itself is covered separately by
  `evals/sparring/` (multi-turn simulated-visitor eval suite, real Anthropic
  calls, own `README.md`), not part of `tests/run.sh`.

## Conventions
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/):
  `type(scope)?: subject`, types `feat fix docs style refactor perf test chore
  build ci revert`, imperative subject, one logical change per commit. A
  `.githooks/commit-msg` hook enforces this — `composer install` wires
  `core.hooksPath` to it automatically (see `docs/getting-started.md` Setup).
  `.githooks/pre-commit` blocks a commit that stages `prompts/sparring.md`
  without `prompts/CHANGELOG.md` (see `prompts/CLAUDE.md`).
- `bin/*.php` CLI scripts: CLI-only guard (`php_sapi_name() !== 'cli'`),
  `require config.php` + relevant `src/*.php`, plain positional `$argv[1]`
  or `in_array('--flag', $argv, true)` — no `getopt()`, no CLI arg library.
  Mirror `bin/export.php`'s shape for new ones.
- `# ponytail: ...` comments already appear in the codebase (e.g.
  `config.php`) marking deliberate minimal-implementation shortcuts — this
  project leans minimal/no-dependency by its own convention.
- Debug/diagnostic flags are client-only (`URLSearchParams(...).get('debug')`);
  the backend has no server-side "mode" concept — it just always returns
  the cheap extra fields and the client decides whether to show them.

## spec/ — design documentation
Behavioral/product design record (AsciiDoc). Details in `spec/CLAUDE.md`.
- New feature touching visible behavior? Check whether spec/ needs an
  update — ask if unsure.

## docs/adr — architecture decisions
Numbered Nygard-lightweight records of implementation-level technical
decisions (stack, tooling, reversible engineering calls) in `docs/adr/`,
indexed by `docs/adr/README.md`. Distinct from `spec/`: the spec records
what must be true about the installation and why; an ADR records how the
code is built. Some ADRs cite a spec `AP-` principle they follow from.
- New non-trivial technical decision → add an ADR (next free number,
  never renumber; a reversed decision gets a new ADR that supersedes the
  old one). Behavioral/product decisions → `spec/`.
