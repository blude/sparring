# 1. Vanilla PHP + SQLite, no framework, no build step

- **Status:** Accepted
- **Date:** Project inception (back-filled 2026-09-10)
- **References:** `CLAUDE.md` "Stack"; `docs/getting-started.md` "Setup"; `spec/LX-system-realization-concept.adoc`

## Context

Sparring is a three-day exhibition proof of concept: two thin browser
clients and one backend, built and maintained by one person on a thesis
timeline. It has to deploy onto a shared EasyEngine host with no Node
toolchain and a PHP that may lag the project's own requirement. Longevity
past the exhibition is not a goal; being trivially inspectable and
runnable on a fresh checkout is.

## Decision

Build on PHP 8.2+ and SQLite with no framework, no bundler, no npm, no
build step. Files are served directly. One `composer` dependency
(`anthropic-ai/sdk`) is allowed because it wraps the one external API;
everything else is standard library or hand-rolled. `public/index.php` is
the sole front controller and doubles as the router script for the PHP
built-in server (`php -S ... public/index.php`), so pretty URLs like
`/dojo` work while real static assets still serve directly. Laravel Valet
routes the same way natively for local development.

## Consequences

- A fresh checkout runs after `composer install` and a `.env`; there is
  nothing to compile and nothing to watch.
- No framework conveniences: routing, request handling, DB access, and
  templating are all explicit in `src/` and `public/`. This is accepted
  as readable rather than terse — see the project's minimal-dependency
  convention and the `# ponytail:` markers already in `config.php`.
- Vanilla JS in `public/assets/` with no transpile means targeting
  browser features directly (iPhone 14+, recent Pixel/Galaxy).
- Commit hygiene is enforced out-of-band: `.githooks/commit-msg` checks
  Conventional Commits, wired by `composer install` setting
  `core.hooksPath`.
- Host PHP version drift is a live operational hazard; `bin/*.php` scripts
  must be run inside the deploy container, not on the host.
