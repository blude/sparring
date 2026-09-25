# 18. Page screenshots for review via chrome-headless-shell, not a visual-diff gate

- **Status:** Accepted
- **Date:** 2026-09-24
- **References:** `bin/screenshots.php`; `.github/workflows/ci.yml`
  `screenshots` job; ADR [0017](0017-fake-llm-provider-and-http-smoke-test.md)
  (fake provider), ADR [0001](0001-vanilla-php-sqlite-no-framework.md) (no
  npm), ADR [0014](0014-ios-keyboard-viewport-fix.md) (iOS viewport)

## Context

Most recent commits are visual (`style(ses)`, `style(dojo)`, `fix(start)`),
and nothing let a reviewer, or an agent that just changed CSS, see the
result without running the app by hand and walking it into each state.

## Decision

- `bin/screenshots.php` seeds a throwaway store directly through `Store`
  (a 16-turn session would otherwise trip the rate limiter), runs the app
  in fake mode, and captures each state by URL (`/dojo?s=<id>` reopens a
  session): phone pages at 390x844, the wall at 1920x1080.
- **Review aid, not a gate.** No baselines are committed and nothing
  fails on a visual change. Style changes are frequent and deliberate, so
  a pixel gate would mostly demand baseline updates; session IDs (and so
  aliases/avatars) also vary per run.
- **chrome-headless-shell, driven from its CLI.** Regular Chrome's
  headless mode won't lay out narrower than 500px, and rendering inside a
  390px iframe instead broke the viewport-height layout at the bottom of
  `/dojo`. Playwright would need npm (ADR 0001). The shell is a standalone
  Chrome for Testing download: CI fetches the stable build, locally it's
  one `npx @puppeteer/browsers install`, not a project dependency.
- Captured with `--force-prefers-reduced-motion`: every stylesheet already
  switches its transitions off under reduced motion, so pages render
  straight to their settled state instead of mid-fade.
- `--simulator` swaps the capture step for `xcrun simctl` in the booted
  iOS Simulator (macOS only, phone pages only), for real iOS Safari
  rendering, which no desktop engine reproduces (ADR 0014).

## Consequences

- CI attaches the screenshots to every PR; a page that can't be captured
  at all fails that job, a changed one never does.
- Shots show settled layouts, not animations, and only the first
  viewport of long pages.
- Only states reachable by URL are covered: anything needing a tap (open
  keyboard, rejection wiggle) isn't, in either backend.
- The Simulator backend has no load event to wait on and waits a fixed
  `SIMULATOR_WAIT` (default 4s) per shot; Safari's own UI is in the frame.
