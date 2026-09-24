# 17. Offline `fake` LLM provider and an end-to-end HTTP smoke test

- **Status:** Accepted
- **Date:** 2026-09-24
- **References:** `src/FakeLlmClient.php`; `config.php` `createLlmClient()`
  and `STORE_DB_PATH`; `tests/smoke_http.php`; `tests/run.sh`; ADR
  [0003](0003-assert-based-smoke-tests.md) (smoke tests), ADR
  [0008](0008-llm-provider-abstraction.md) (provider abstraction)

## Context

Every smoke test stopped below the HTTP layer: `smoke_sparring.php`
drives `Sparring` directly with an in-test fake, and nothing exercised the
router, the `public/api/*.php` endpoints, or their status-code mapping. Checking
a UI or API change end to end meant a real provider, so an API key, network
and cost, with the failure paths (moderation rejections, generation errors)
reachable only by luck.

Writing this test also turned up that `assert()` never ran in
production-configured PHP (`zend.assertions=-1`, the production
`php.ini` default and GitHub's `setup-php` default), so every PHP smoke
test passed without checking anything there. The suite did pass once
assertions were on.

## Decision

- A third `LLM_PROVIDER` value, `fake`: `FakeLlmClient` returns
  deterministic replies prefixed `(fake)`, and markers in a contribution
  (`[fake:personal]`, `[fake:real-person]`, `[fake:classify-error]`,
  `[fake:generation-error]`) trigger each failure path on demand. It's
  loaded lazily inside `createLlmClient()`, so normal requests never load it.
- `STORE_DB_PATH` can be overridden from the real environment, so a test
  can run the app against a throwaway database.
- `tests/smoke_http.php` starts the real app under `php -S` with the fake
  provider and a temp database, then drives the pages and API calls the
  clients use over HTTP. It stays a plain assert script, per ADR 0003.
- `tests/run.sh` runs PHP with `-d zend.assertions=1`, and each PHP smoke
  script exits non-zero if assertions are off.

## Consequences

- UI and API changes can be checked end to end locally, in CI and in cloud
  agent sessions with no key and no network beyond localhost.
- ADR 0003's "no real network call" boundary still holds: the HTTP test
  only talks to its own `php -S` on 127.0.0.1.
- The fake is a dev/test tool, not a degraded mode: nothing selects it
  automatically, and its `(fake)` prefix makes a misconfigured wall obvious.
- Running a PHP smoke script by hand now needs `php -d zend.assertions=1`.
