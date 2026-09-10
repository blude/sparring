# 11. Debug flags are client-only; the backend has no "mode" concept

- **Status:** Accepted
- **Date:** 2026-08 (back-filled 2026-09-10)
- **References:** `CLAUDE.md` "Conventions"; `README.md` "Database maintenance" (`?debug=1`)

## Context

The input and display clients have a diagnostics panel — session id,
origin, rate-limit remaining, generation timing, moderation reason on a
flagged contribution. The question is where "debug mode" lives.

## Decision

Debug is a client-only concern, read from the URL
(`URLSearchParams(...).get('debug')`). The backend has no server-side mode
switch. It always returns the cheap extra diagnostic fields on its normal
responses, and the client decides whether to render the panel.

## Consequences

- No environment flag, no per-request mode negotiation, no branch in the
  backend between "normal" and "debug" responses. One response shape.
- The extra fields are on every response, including production. They are
  cheap and non-sensitive (timing, a rate-limit count, a moderation
  reason string) by deliberate choice; anything that would be unsafe to
  always return does not go in them.
- Turning on diagnostics in the field is appending `?debug=1` to a URL,
  nothing else.
