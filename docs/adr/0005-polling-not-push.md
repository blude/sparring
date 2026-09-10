# 5. Polling, not push, for the wall display

- **Status:** Accepted
- **Date:** 2026-08 (back-filled 2026-09-10)
- **References:** `spec/L2-system-design-concept.adoc` AP-02

## Context

The wall (SE-02) has to show new exchanges shortly after they complete.
The obvious "real-time" answers are server-sent events or websockets. The
backend is a plain PHP application behind nginx on a shared host, with no
long-lived process model and no build step.

## Decision

The display polls the backend on an interval. No SSE, no websockets. A
few seconds of latency between a turn completing and its appearance on the
wall is accepted.

## Consequences

- No persistent-connection infrastructure, no reconnect logic, no
  process-supervision requirement on the host. A poll is a normal stateless
  request the existing stack already serves.
- The wall can be a few seconds behind the phone. For an installation
  where a room watches one slow argument unfold, this is imperceptible.
- Message streaming to the phone is a separate, larger question and is
  deferred for the same infrastructure reasons (see `TODO.md`).
