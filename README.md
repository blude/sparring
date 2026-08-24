# Sparring Exhibition Object — POC

Implements the L1–LX design spec in `spec/`. See
`.claude/plans/` (or ask Claude) for the build plan, gap analysis, and the
resolved moderation-flow decision. Status: proof of concept — rudimentary
input/display clients, backend, LLM integration, display selection, session
lifecycle, pilot seed + export. Non-functional polish (display layout
treatment, visual design) is explicitly deferred per the specs themselves.

## Setup

```sh
composer install                       # pulls anthropic-ai/sdk into vendor/
cp .env.example .env                   # then edit it, ANTHROPIC_API_KEY=sk-ant-...
sh tests/run.sh                        # runs all 10 smoke tests (PHP + Node), no API key needed
php bin/import_pilot.php               # M1: seeds data/store.db from data/pilot/*.json
php bin/import_curriculum.php          # syncs curriculum_chunks FTS5 table from data/curriculum/*.md
valet link                             # once per checkout; serves this dir at https://sparring.test
```

`composer install` also wires `core.hooksPath` to `.githooks/` (Conventional Commits check on `git commit`).

Preferred dev method is Valet — it's a real php-fpm SAPI, same as prod, so
it doesn't inherit a shell's `export` and needs the `.env` file (`config.php`
loads it on every request; a real env var still wins if both are set).
`valet link` reads the folder name (`sparring`) as the site name; run it
once from the repo root, then open `https://sparring.test/dojo` (SE-01)
and `https://sparring.test/arena` (SE-02). `LocalValetDriver.php` at the
repo root makes Valet route the same way as the command below.

Alternative — no Valet, plain PHP built-in server:

```sh
export ANTHROPIC_API_KEY=sk-ant-...    # inherits from the shell here, .env not required
php -S localhost:8080 -t public public/index.php   # serves SE-01 + SE-02 + the API
```

Then open `http://localhost:8080/dojo` and `http://localhost:8080/arena`.

## Switch LLM provider

Default is Anthropic — no config needed. To use OpenAI, or a local model
served through LM Studio, add to `.env`:

```sh
LLM_PROVIDER=openai
OPENAI_API_KEY=sk-...                        # leave unset for LM Studio, any value works
OPENAI_BASE_URL=http://localhost:1234/v1     # omit for real OpenAI (defaults to api.openai.com)
OPENAI_GENERATION_MODEL=qwen2.5-7b-instruct  # whatever model LM Studio has loaded
OPENAI_CLASSIFICATION_MODEL=qwen2.5-7b-instruct
```

`OPENAI_GENERATION_MODEL`/`OPENAI_CLASSIFICATION_MODEL` default to
`gpt-4.1`/`gpt-4.1-mini` if unset — fine for real OpenAI, but LM Studio needs
whatever model ID it has loaded, so set these explicitly for local use.
See `config.php`'s `--- LLM (PE-01) ---` block for every var name/default.

Either way, `public/index.php` is the sole front controller — see
`public/index.php` for the route table. Real static assets (e.g.
`/assets/dojo.js`) still serve directly; page/endpoint scripts do not —
`/dojo.php` 404s, only the pretty URL `/dojo` works. The display feed
works immediately off the pilot seed; the input client needs
`ANTHROPIC_API_KEY` set and `composer install` run, since it calls the LLM.

## Export

```sh
php bin/export.php dump.json    # full raw store dump; omit the arg to print to stdout
```

## Database maintenance

```sh
php bin/backup_db.php                  # snapshot to data/backups/store-<timestamp>.db
php bin/backup_db.php path/to/file.db  # snapshot to a specific path instead

php bin/reset_db.php --dry-run  # report what would be deleted, changes nothing
php bin/reset_db.php --confirm  # empty sessions/exchanges/rate-limit tables

php bin/prune_orphaned_sessions.php --dry-run  # count sessions with zero exchanges, inactive 24h+
php bin/prune_orphaned_sessions.php --confirm  # delete them (never touches a session with any exchange)
```

On prod, run any `bin/*.php` script through `ee shell <site> --command='php bin/...'`
instead of bare `php bin/...` on the host — the host's PHP may be older than
this project's 8.2+ requirement (see Deploy below).

`?debug=1` on `/dojo`/`/arena` shows a diagnostics panel (session
id, origin, rate-limit remaining, generation timing, moderation reason on a
flagged contribution) — dev-only, off by default.

## Layout

See the plan's "File layout" section. Everything under `src/` and `prompts/`
and `data/` must sit outside the web-served directory on the real host —
`public/` is the only directory EasyEngine's nginx should serve.

## Deploy

```sh
cp bin/deploy.env.example bin/deploy.env   # once; fill in your host/path, git-ignored
bin/deploy.sh --dry-run                    # preview the rsync, no writes
bin/deploy.sh                              # sync to prod, then composer install in the site's container
```

Host/path/site name live in `bin/deploy.env`, not the script.

Syncs this repo to the EasyEngine site's app root (`htdocs/`).

First deploy on a fresh site, by hand, once:
- create `htdocs/.env` (see Setup above — `config.php` only reads that path,
  not EasyEngine's site-root `.env`)
- seed `data/store.db` — run it **inside the container**, not on the host:
  host PHP may be older than this project's 8.2+ requirement (was PHP 8.0
  vs. the container's 8.4.22 here, a parse error on readonly-property/enum
  syntax was the symptom)
  ```sh
  ssh <host> "ee shell <site> --command='php bin/import_pilot.php'"
  ```
- confirm `data/` is writable by the container's PHP-FPM user

## Known gaps before opening night (see plan doc §7)

- SC-06's display layout: TBC-01 (masonry) is implemented as the working
  baseline in `public/assets/arena.js`. TBC-02…06 (dwell time, which exchange
  pair, item count/truncation, pilot-origin marking) are still open per the
  spec's own acceptance criteria — to be resolved against real pilot
  transcripts on the actual projector.
