# 19. A third session origin, `study`, set by hand and migrated by table rebuild

- **Status:** Accepted
- **Date:** 2026-10-03
- **References:** `src/Store.php` (`migrate()`, `getDisplayableSessions()`);
  `src/Sparring.php` (`assembleDisplayMaterial()`); spec `SQR-05`, `E-01.4`,
  `FS-04-1`

## Context

Some sessions were conducted during the sparring evaluation study and need
to be told apart from exhibition sessions in the data. `sessions.origin`
only allowed `pilot` and `live`, via a CHECK constraint, so a manual edit to
`study` was rejected by SQLite.

## Decision

- Add `study` to the origin enum (CHECK constraint and `createSession()`'s
  whitelist). Study sessions are created as `live` like any other and the
  operator relabels them by hand. There is no app code path or script for
  it: a one-off, manual step, so no `bin/` tool for it.
- SQLite can't alter a CHECK in place, so `migrate()` rebuilds `sessions`
  when its stored SQL lacks `'study'`: create `sessions_new`, copy, drop,
  rename, inside a transaction with foreign keys off (otherwise the DROP
  cascades into `session_evaluations` and trips the `exchanges` FK).
  Idempotent; runs on any `store.db`, local or deployed, at first start.
- Study sessions are projected like live ones: `assembleDisplayMaterial()`
  ranks `live` and `study` together by recency, then fills with `pilot`.
  Projection consent (`displayable`) already gates what shows, so no
  study-specific rule is needed.
- `getDisplayableSessions()` takes a list of origins instead of one.

## Consequences

- Origin is no longer strictly write-once; `SQR-05` and `E-01.4` say the
  `live` to `study` relabeling is the one exception, and an operator action.
- The rebuild has to list the columns explicitly, since ALTER-added columns
  sit in a different order on older files. A new `sessions` column means
  updating both the DDL closure and that list.
- The rebuild is not guarded against two requests hitting a not-yet-migrated
  file at the same instant (both would rebuild; the second is a harmless
  redo). Accepted: it runs once, at deploy, at this project's traffic. If it
  ever matters, `BEGIN IMMEDIATE` and re-check the schema inside it.
- `bin/export.php` already dumps every origin, so study sessions are
  distinguishable in exports with no change.
