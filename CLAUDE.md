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
- Test: assert-based smoke scripts, no framework. `tests/run.sh` runs all of
  them (PHP then Node, each its own process — see the script's own comment on
  why), stops on first failure. Individually — PHP: `php tests/smoke_store.php`
  (`Store`), `php tests/smoke_llm_client.php` (provider dispatch +
  `OpenAiLlmClient`/`AnthropicLlmClient`'s pure response/failure-classification
  helpers), `php tests/smoke_sparring.php` (`Sparring::processTurn`'s gates,
  `sessionStateFor`/`isExpired`, and `RateLimiter::resolveClientOrigin`),
  `php tests/smoke_domain.php`
  (`derive_scenario_statement`, `AbstractLlmClient::stripDelimiterTag`,
  `bin/import_pilot.php::validate_transcript`). JS (Node only to run the
  check, not a project dependency): `node tests/smoke_juicy.js`
  (`public/assets/juicy.js`), `node tests/smoke_mermaid.js` (fence-detection
  in `public/assets/mermaid-render.js`), `node tests/smoke_identity.js`
  (alias/avatar seed in `public/assets/identity.js`), `node tests/smoke_dojo.js`
  (`handleContributionResult`'s outcome table in `public/assets/dojo.js`),
  `node tests/smoke_arena.js` (the wall's add/update/remove diff in
  `public/assets/arena.js`), and `node tests/smoke_sfx.js` (note-duration/
  chord-detection logic in `public/assets/sfx.js`).
- No linter configured — check changed files with `php -l <file>`.

## Conventions
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
ID scheme (`BG-`, `SG-`, `G-`, `UC-`, `TF-`, `QR-`, `C-`, etc.) —
**hand-written Markdown mimicking StrictDoc IDs, not a real validated
StrictDoc project** (no `.sgra`/`.sdoc` files, don't run `strictdoc` here).
- Only behavioral/decision content gets modeled. Static pages
  (`privacy.php`, `terms.php`) and single-purpose CLI ops scripts
  (`bin/export.php`) are **intentionally unmodeled** — covered by existing
  generic constraint language (see `C-04` in `L3-SE-03-backend-service.md`)
  rather than a dedicated UC/TF/QR that would just restate its parent.
- New feature touching visible behavior? Check whether spec/ needs an
  update — ask if unsure.

## prompts/sparring.md
Editing this file: log it in `prompts/CHANGELOG.md` under `## Unreleased`.
On commit, move that entry under a new dated/commit-hash heading.