# Getting started

Local setup for development. For export, database maintenance and deployment,
see [Operations](operations.md).

## Requirements

- PHP 8.2+ with `pdo_sqlite` (SQLite with FTS5)
- Composer
- Node, only to run the JS smoke tests (not a project dependency)
- An Anthropic API key, or another provider (see [LLM providers](#llm-providers))

## Setup

```sh
composer install                       # pulls anthropic-ai/sdk into vendor/
cp .env.example .env                   # then edit it, ANTHROPIC_API_KEY=sk-ant-...
sh tests/run.sh                        # runs all smoke tests (PHP + Node), no API key needed
php bin/import_pilot.php               # seeds data/store.db from data/pilot/*.json
```

`composer install` also wires `core.hooksPath` to `.githooks/`. That adds a
Conventional Commits check on `git commit`, plus a pre-commit check that a
`prompts/sparring.md` change carries a `prompts/CHANGELOG.md` entry.

## Run

### Laravel Valet (preferred)

```sh
valet link                             # once per checkout; serves this dir at https://sparring.test
```

Valet is a real php-fpm SAPI, same as prod. It doesn't inherit a shell's
`export`, so it needs the `.env` file (`config.php` loads it on every request;
a real env var still wins if both are set). `valet link` reads the folder name
(`sparring`) as the site name. Open `https://sparring.test/dojo` (SE-01) and
`https://sparring.test/arena` (SE-02). `LocalValetDriver.php` at the repo root
makes Valet route the same way as the built-in server below.

### PHP built-in server

```sh
export ANTHROPIC_API_KEY=sk-ant-...    # inherits from the shell here, .env not required
php -S localhost:8080 -t public public/index.php   # serves SE-01 + SE-02 + the API
```

Then open `http://localhost:8080/dojo` and `http://localhost:8080/arena`.

### Routing

`public/index.php` is the sole front controller; see it for the route table.
Real static assets (e.g. `/assets/dojo.js`) still serve directly, but
page/endpoint scripts do not: `/dojo.php` 404s, and only the pretty URL `/dojo`
works. The display feed works immediately off the pilot seed. The input client
calls the LLM, so it needs a key (or `LLM_PROVIDER=fake`, see below).

Everything under `src/`, `prompts/` and `data/` must sit outside the web-served
directory on a real host. `public/` is the only directory the web server should
serve.

`?debug=1` on `/dojo` or `/arena` shows a diagnostics panel (session id,
origin, rate-limit remaining, generation timing, moderation reason on a flagged
contribution). It's dev-only and off by default.

## LLM providers

Default is Anthropic, no config needed. To use OpenAI, or a local model served
through LM Studio, add to `.env`:

```sh
LLM_PROVIDER=openai
OPENAI_API_KEY=sk-...                        # leave unset for LM Studio, any value works
OPENAI_BASE_URL=http://localhost:1234/v1     # omit for real OpenAI (defaults to api.openai.com)
OPENAI_GENERATION_MODEL=qwen2.5-7b-instruct  # whatever model LM Studio has loaded
OPENAI_CLASSIFICATION_MODEL=qwen2.5-7b-instruct
```

`OPENAI_GENERATION_MODEL`/`OPENAI_CLASSIFICATION_MODEL` default to
`gpt-4.1`/`gpt-4.1-mini` if unset. That's fine for real OpenAI, but LM Studio
needs whatever model ID it has loaded, so set these explicitly for local use.
See `config.php`'s `--- LLM (PE-01) ---` block for every var name and default.

### Fake provider (offline)

For UI work, or a demo without a network connection, `LLM_PROVIDER=fake`
answers every turn with a canned, clearly marked `(fake) ...` reply, instantly
and for free. Never use it for the exhibition.

```sh
LLM_PROVIDER=fake php -S localhost:8080 -t public public/index.php
```

Markers in a contribution trigger the failure paths:

| Marker | Effect |
|---|---|
| `[fake:personal]`, `[fake:real-person]` | moderation rejects it |
| `[fake:classify-error]` | moderation fails closed |
| `[fake:generation-error]` | 502 |

`FAKE_LLM_DELAY_MS=1500` adds latency, so you can see the waiting states.
`tests/smoke_http.php` runs the whole app this way.

## Curriculum corpus

On turn 1, the reply can be grounded in a local curriculum corpus
(`data/curriculum/*.md`, not included in the repository). The corpus lives in
the `curriculum_chunks` SQLite FTS5 table:

```sh
php bin/import_curriculum.php          # syncs curriculum_chunks from data/curriculum/*.md
php bin/clear_curriculum.php           # empties curriculum_chunks (disk-derived cache, safe to redo)
php bin/probe_curriculum.php "<text>"  # prints what curriculum_chunks would surface for <text>
```

Without a corpus, the app runs normally and turn 1 is simply not grounded.

## Tests

```sh
sh tests/run.sh
```

Runs every `tests/smoke_*` script (PHP, then Node) and stops on the first
failure. The tests are assert-based, with no framework (ADR 0003), and never
call a real LLM. To run a single PHP script, use
`php -d zend.assertions=1 tests/<file>`, because production `php.ini` compiles
`assert()` out. There's no linter; check changed files with `php -l <file>`.

CI (`.github/workflows/ci.yml`) runs the same checks, the smoke tests and a
`public/spec/` drift check on every push to `develop`/`main` and on every PR.

The conversational quality of `prompts/sparring.md` is covered separately by
[`evals/sparring/`](../evals/sparring/README.md), which uses real API calls.

## Screenshots

`php bin/screenshots.php` captures every visitor-facing page in fixed, seeded
states into `screenshots/`, with an `index.html` gallery. That covers the
start page, the consent step, a session mid-way and one at its turn limit,
the content pages, the German start page, the wall at 1920x1080, and the
wall's beamer profile at 1600x900. It runs the app in fake mode against a
throwaway database, so it needs no API key and never touches `data/store.db`.
It's a review aid for visual changes and doesn't compare against a baseline
(ADR 0018). CI attaches the same set to every PR as the `screenshots` artifact.

It needs **chrome-headless-shell**, not regular Chrome, whose headless mode
won't render narrower than 500px. One-off install, no project dependency:

```sh
npx @puppeteer/browsers install chrome-headless-shell@stable
export CHROME_BIN=<the path it prints>
php bin/screenshots.php
```

On a Mac with Xcode, `--simulator` captures the phone pages in Safari in the
booted iOS Simulator instead. That gives real iOS rendering, including the
keyboard and viewport behaviour:

```sh
xcrun simctl boot "iPhone 16" && open -a Simulator
php bin/screenshots.php --simulator screenshots-ios
```

## Design spec

The spec in `spec/` is AsciiDoc, built to `public/spec/` with
`bin/build_spec.sh` (Asciidoctor). `bin/build_spec_pdf.sh` builds the same
content as one PDF book (ADR 0015).
