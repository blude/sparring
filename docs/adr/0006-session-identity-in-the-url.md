# 6. Session identity travels in the URL; no accounts, no identity cookies

- **Status:** Accepted
- **Date:** 2026-08 (back-filled 2026-09-10)
- **References:** `spec/L2-system-design-concept.adoc` AP-03; `TODO.md` (session title as identifier)

## Context

A visitor arrives by scanning a QR code, uses the piece for a few minutes,
and leaves. There is no reason for them to have an account, and identity
cookies on a shared-context phone page carry privacy and consent baggage
the installation should avoid.

## Decision

Session identity travels in the URL. No login, no registration, no
identity cookie. A session is created server-side and its identifier is
carried in the link the visitor holds; the human-readable session title
doubles as the de facto identifier for exchanges shown in the arena.

## Consequences

- Losing the URL loses the session. For a walk-up exhibition piece that is
  acceptable and even desirable — sessions are meant to be ephemeral.
- Consent state (display eligibility, retention, participation) is stored
  as distinct per-session properties server-side, not inferred from a
  cookie.
- No cross-session identity means no "my history" feature; export is an
  operator action (`bin/export.php`), not a visitor one.
