# 4. All logic and credentials server-side; clients are presentation only

- **Status:** Accepted
- **Date:** 2026-08 (back-filled 2026-09-10)
- **References:** `spec/L2-system-design-concept.adoc` AP-01

## Context

The system is client-server with three participants: the visitor's phone
(SE-01), the projected wall (SE-02), and one backend (SE-03). The phone
client is loaded from a QR code onto an untrusted device in a public
space. The backend holds the language-API key.

## Decision

Keep all logic and all credentials in the backend. The browser clients do
presentation only: render, capture input, poll. Gating, moderation,
session policy, rate limiting, retrieval, and the single outbound LLM call
all live server-side. No secret and no policy decision is shipped to a
client.

## Consequences

- A hostile phone client can send arbitrary turns but cannot bypass a
  gate, read a key, or change session policy — the server re-checks
  everything on every turn.
- The clients stay thin and replaceable; SE-01 and SE-02 are "rudimentary"
  by the spec's own framing and that is fine.
- Every product rule has exactly one enforcement point, on the server.
  This is the root that ADR 0011 (no client-trusted "mode") builds on.
