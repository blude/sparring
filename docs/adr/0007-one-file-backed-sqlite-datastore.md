# 7. One file-backed SQLite datastore in WAL mode, inside the backend

- **Status:** Accepted
- **Date:** 2026-08 (back-filled 2026-09-10)
- **References:** `spec/L2-system-design-concept.adoc` AP-04; `CLAUDE.md` "Stack"; `src/Store.php`

## Context

The backend needs to persist sessions, exchanges, evaluations, rate-limit
counters, and an FTS index over the curriculum corpus. Expected load is a
single room of visitors over three days — low concurrency, small data.

## Decision

Use one SQLite database file (`data/store.db`) in WAL mode, owned entirely
by the backend element. No separate database server. All access goes
through `src/Store.php`. `data/` sits outside the web-served `public/`
directory on the real host.

## Consequences

- Deployment is a file copy plus a seed script; backup is
  `bin/backup_db.php` snapshotting the file. No DB service to run,
  secure, or monitor.
- WAL mode gives concurrent reads during a write, enough for the polling
  display to read while a turn is being persisted.
- `data/` must be writable by the container's PHP-FPM user, and must never
  be served by nginx — both are explicit deploy checklist items.
- Scaling past a single low-traffic host would require revisiting this;
  out of scope for the exhibition.
