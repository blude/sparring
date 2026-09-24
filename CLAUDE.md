# CLAUDE.md - Sparring Live

Thesis exhibition POC: visitor argues with an AI on their phone (SE-01), the
exchange projects on a wall (SE-02), one backend service (SE-03). See
`README.md` for setup/run, `spec/` for the design.

## Stack
- PHP 8.2+, vanilla JS, SQLite (`data/store.db`, WAL mode). No build step,
  no npm, no framework, no bundler — files served directly.
- Run: `composer install && export ANTHROPIC_API_KEY=... && php -S localhost:8080 -t public public/index.php`
  (the trailing `public/index.php` is the router script — pretty URLs like
  `/input` need it, real files still serve directly). Also runs under
  Laravel Valet (`valet park`/`link`), which routes the same way natively.
  `LLM_PROVIDER=fake` runs it offline with no key (canned `(fake)` replies,
  markers for the failure paths): use it to check UI or API changes end to
  end without real calls. See README "Switch LLM provider".
- Test: assert-based smoke scripts, no framework. `tests/run.sh` runs all of
  them (PHP then Node, each its own process — see the script's own comment on
  why), stops on first failure. Individually — PHP needs
  `php -d zend.assertions=1 tests/<file>` (production php.ini compiles
  `assert()` out; each PHP script refuses to run without it):
  `smoke_store.php`
  (`Store`), `smoke_llm_client.php` (provider dispatch, `FakeLlmClient`'s markers, +
  `OpenAiLlmClient`/`AnthropicLlmClient`'s pure response/failure-classification
  helpers, and each's `buildSystemMessages()`/`buildSystemBlocks()` — how
  turn-1 curriculum grounding does/doesn't get attached without disturbing
  the cached sparring-prompt block), `smoke_sparring.php`
  (`Sparring::processTurn`'s gates, `sessionStateFor`/`isExpired`,
  `RateLimiter::resolveClientOrigin`, and turn-1-only curriculum grounding),
  `smoke_domain.php`
  (`derive_scenario_statement`, `AbstractLlmClient::stripDelimiterTag`,
  `bin/import_pilot.php::validate_transcript`,
  `bin/import_curriculum.php::parse_curriculum_file`),
  `smoke_curriculum_retrieval.php` (`Store::searchCurriculum()`
  retrieval quality — stopword filtering, title-weighted BM25, prefix
  matching — and `Store::searchCurriculumConcepts()`'s vocabulary-restricted
  turn-1 auto-grounding lookup, against `tests/fixtures/curriculum/`,
  verbatim excerpts of the real, gitignored `data/curriculum/` corpus, kept
  small and committed so this stays reproducible on a fresh checkout),
  `smoke_i18n.php` (`config.php`'s locale resolution, `t()`, and
  `i18n/en.php`/`i18n/de.php` catalog parity), `smoke_http.php` (end to end:
  the real app under `php -S` with `LLM_PROVIDER=fake` and a throwaway
  `STORE_DB_PATH`, driven over HTTP through the pages and the session/contribute/
  title/session-state/recent-exchanges calls, including each contribute
  error path). JS
  (Node only to run the
  check, not a project dependency): `node tests/smoke_juicy.js`
  (`public/assets/juicy.js`), `node tests/smoke_identity.js`
  (alias/avatar seed in `public/assets/identity.js`), `node tests/smoke_dojo.js`
  (`handleContributionResult`'s outcome table in `public/assets/dojo.js`),
  `node tests/smoke_arena.js` (the wall's add/update/remove diff in
  `public/assets/arena.js`), `node tests/smoke_sfx.js` (note-duration/
  chord-detection logic in `public/assets/sfx.js`), and
  `node tests/smoke_start.js` (the glove easter egg's tap-window helper in
  `public/assets/start.js`). New `tests/smoke_*` files are picked up by
  `tests/run.sh` automatically; add a line here too.
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
  don't install by hand. `.claude/settings.json` pre-allows
  the test/lint commands and denies `bin/deploy*.sh` and the destructive
  `bin/` data scripts (reset/delete/prune) for agents.
- `tests/run.sh` is plumbing only — it never calls a real LLM. Conversational/
  pedagogical quality of `prompts/sparring.md` itself is covered separately by
  `evals/sparring/` (multi-turn simulated-visitor eval suite, real Anthropic
  calls, own `README.md`), not part of `tests/run.sh`.

## Conventions
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/):
  `type(scope)?: subject`, types `feat fix docs style refactor perf test chore
  build ci revert`, imperative subject, one logical change per commit. A
  `.githooks/commit-msg` hook enforces this — `composer install` wires
  `core.hooksPath` to it automatically (see README Setup).
  `.githooks/pre-commit` blocks a commit that stages `prompts/sparring.md`
  without `prompts/CHANGELOG.md` (see the last section).
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
Four-level framework (L0 brief, L1 solution, L2 system, L3 per-element),
ID scheme (`BG-`, `SG-`, `G-`, `UC-`, `TF-`, `QR-`, `C-`, etc.), written
in AsciiDoc and built to `public/spec/` with `bin/build_spec.sh`.
`bin/build_spec_pdf.sh` builds the same content as one combined book,
`public/spec/sparring-spec.pdf` (gitignored, built on demand — it
pre-renders the Mermaid blocks via `npx` mermaid-cli, see
`docs/adr/0015-spec-pdf-book-target.md`).
- Only behavioral/decision content gets modeled. Static pages
  (`privacy.php`, `terms.php`) and single-purpose CLI ops scripts
  (`bin/export.php`) are **intentionally unmodeled** — covered by existing
  generic constraint language (see `C-04` in `L3-SE-03-backend-service.adoc`)
  rather than a dedicated UC/TF/QR that would just restate its parent.
- New feature touching visible behavior? Check whether spec/ needs an
  update — ask if unsure.
- `prompts/sparring.md` is modeled as `SE-04` (`spec/L3-SE-04-system-prompt.adoc`)
  — a content-supplying element with no runtime interface of its own.
  Behavioral edits to the prompt should be checked against it too.

## docs/adr — architecture decisions
Numbered Nygard-lightweight records of implementation-level technical
decisions (stack, tooling, reversible engineering calls) in `docs/adr/`,
indexed by `docs/adr/README.md`. Distinct from `spec/`: the spec records
what must be true about the installation and why; an ADR records how the
code is built. Some ADRs cite a spec `AP-` principle they follow from.
- New non-trivial technical decision → add an ADR (next free number,
  never renumber; a reversed decision gets a new ADR that supersedes the
  old one). Behavioral/product decisions → `spec/`.

## prompts/sparring.md
Editing this file: log it in `prompts/CHANGELOG.md` under `## Unreleased`.
On commit, move that entry under a new dated/commit-hash heading.