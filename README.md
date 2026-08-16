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
export ANTHROPIC_API_KEY=sk-ant-...    # never put this in config.php
php tests/smoke_store.php              # M0 self-check, no API key needed
php bin/import_pilot.php               # M1: seeds data/store.db from data/pilot/*.json
php -S localhost:8080 -t public public/index.php   # serves SE-01 + SE-02 + the API
```

`public/index.php` is passed as the router script and is the sole front
controller — see `public/index.php` for the route table. Real static assets
(e.g. `/assets/input.js`) still serve directly; page/endpoint scripts do
not — `/input.php` 404s, only the pretty URL `/input` works. Valet gets the
same behavior from `LocalValetDriver.php` at the repo root.

Then open `http://localhost:8080/input` (SE-01) and
`http://localhost:8080/display` (SE-02) in two tabs. The display feed
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
```

`?debug=1` on `/input`/`/display` shows a diagnostics panel (session
id, origin, rate-limit remaining, generation timing, moderation reason on a
flagged contribution) — dev-only, off by default.

## Layout

See the plan's "File layout" section. Everything under `src/` and `prompts/`
and `data/` must sit outside the web-served directory on the real host —
`public/` is the only directory EasyEngine's nginx should serve.

## Known gaps before opening night (see plan doc §7)

- `data/profanity_terms.txt` is a two-word placeholder. Replace with a real list.
- `prompts/sparring.md` and `prompts/moderation.md` are drafts — read and
  rewrite before the exhibition; they're the actual pedagogical content.
- Rate-limit key (`RateLimiter::resolveClientOrigin`) trusts
  `X-Forwarded-For` — verified on a sibling EasyEngine site (2026-08-07) that
  nginx sets this correctly and overwrites spoofed values. Still needs the
  same spoof check run against sparring-live's own droplet before opening
  night, in case that site's nginx config differs.
- SC-06's display layout (TBC-01…06) is untouched — current SE-02 baseline is
  intentionally minimal per the spec, to be refined against real transcripts
  on the actual projector.
