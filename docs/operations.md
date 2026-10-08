# Operations

Running the installation: exporting transcripts, maintaining the database and
deploying. For local setup, see [Getting started](getting-started.md).

On prod, run any `bin/*.php` script through
`ee shell <site> --command='php bin/...'` instead of bare `php bin/...` on the
host. The host's PHP may be older than this project's 8.2+ requirement (see
[Deploy](#deploy)).

## Export

```sh
php bin/export.php dump.json                       # full raw store dump; omit the arg to print to stdout
php bin/export.php --markdown out.md               # one concatenated transcript document
php bin/export.php --markdown transcripts/         # dir target: one <date>-<id>.md per session
php bin/export.php --markdown --evaluation out.md  # also append each visitor's end-of-session evaluation
```

A directory output path for `--markdown` (an existing directory, or a path
ending in `/`) splits the export into one file per session instead of a single
document. `--evaluation` adds an `## Evaluation` section (ratings and feedback)
to each session whose visitor submitted one. See `bin/export.php`'s header for
the other formats (`--jsonl`, `--csv`, `--index`) and flags (`--session=`,
`--consented-only`).

## Database maintenance

The store is a single SQLite file, `data/store.db` (WAL mode).

```sh
php bin/backup_db.php                  # snapshot to data/backups/store-<timestamp>.db
php bin/backup_db.php path/to/file.db  # snapshot to a specific path instead
```

The scripts below delete data. Each one has a `--dry-run` that reports what it
would delete, and does nothing without `--confirm`. Back up first.

```sh
php bin/reset_db.php --dry-run                 # report what would be deleted
php bin/reset_db.php --confirm                 # empty sessions/exchanges/rate-limit tables

php bin/prune_orphaned_sessions.php --dry-run  # count sessions with zero exchanges, inactive 24h+
php bin/prune_orphaned_sessions.php --confirm  # delete them (never touches a session with any exchange)

php bin/delete_session.php <id> --dry-run      # report what would be deleted for one session
php bin/delete_session.php <id> --confirm      # delete that session + its exchanges + its evaluation
```

### Session origin

Each session's origin is `live`, `pilot` or `study`. Sessions conducted during
the evaluation study are marked by hand, without a script (ADR 0019). Back up
first:

```sh
php bin/backup_db.php
sqlite3 data/store.db "UPDATE sessions SET origin='study' WHERE id IN ('<id>', '<id>')"
```

Starting the app once on an older `store.db` migrates it to accept `study`.
It's a one-time table rebuild that keeps all rows.

## Deploy

The production host is an [EasyEngine](https://easyengine.io/) site.
`bin/deploy.sh` syncs this repo to the site's app root (`htdocs/`) with rsync,
then runs `composer install` in the site's container.

```sh
cp bin/deploy.env.example bin/deploy.env   # once; fill in your host/path, git-ignored
bin/deploy.sh --dry-run                    # preview the rsync, no writes
bin/deploy.sh                              # sync to prod, then composer install in the site's container
```

Host, path and site name live in `bin/deploy.env`, not in the script. Only
`public/` should be web-served; `src/`, `prompts/` and `data/` sit outside the
docroot.

First deploy on a fresh site, by hand, once:

- Create `htdocs/.env` (see [Getting started](getting-started.md#setup)).
  `config.php` only reads that path, not EasyEngine's site-root `.env`.
- Seed `data/store.db` **inside the container**, not on the host. Host PHP may
  be older than 8.2; here it was PHP 8.0, against 8.4 in the container, and the
  symptom was a parse error on readonly-property/enum syntax.
  ```sh
  ssh <host> "ee shell <site> --command='php bin/import_pilot.php'"
  ```
- Confirm that `data/` is writable by the container's PHP-FPM user.
