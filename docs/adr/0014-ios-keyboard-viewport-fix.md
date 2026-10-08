# 14. iOS keyboard viewport handled with `--vvh` + scroll-lock, not a hand-rolled handler

- **Status:** Accepted
- **Date:** 2026-09-03 (commits `21d79d6`, `233e03d`; follow-ups `2aeae37`)
- **References:** `docs/notes/ios-keyboard-viewport.md`; memory note `ios-keyboard-viewport-dont-handroll`; `public/assets/dojo.js`

## Context

On iOS Safari, opening the software keyboard shifts the visual viewport
and pushes the input client's fixed top bar off screen. Root cause, pinned
with real numbers on iOS 26: the keyboard leaves `100dvh`, `vh`, `%`, and
the CSS layout viewport all at full size — only `window.visualViewport.height`
shrinks. So `<main>` stayed taller than the visible band, became
scrollable, and iOS reveal-scrolled the whole document to lift the
composer, shoving the top bar off the top.

An earlier effort spent roughly eight iterations hand-rolling a
`visualViewport` handler (sticky, `interactive-widget` meta, body height
clamp, `translateY` chase, `position:fixed` on body and on `<main>`,
scroll-pin loops) and never landed — iOS fires `resize`/`scroll` on
inconsistent beats and `overflow:hidden` on the root does not stop the
reveal-scroll.

## Decision

Do not improvise from `visualViewport` primitives. The fix that works is
three parts, all needed:

1. `body { height: var(--vvh, 100dvh) }`; `dojo.js` sets `--vvh` to
   `visualViewport.height` on the `resize` event.
2. `#history { min-height: 0 }` so `flex:1` can actually shrink it and
   `<main>` never overflows the shortened `<body>`.
3. A `window` `scroll` listener that hard-undoes any `scrollX`/`scrollY`,
   cancelling iOS's reveal-scroll. Safe because the page never scrolls
   legitimately (`html,body{overflow:hidden}`, `#history` is the only
   scroller).

Both `--vvh` and the scroll-lock guard on `visualViewport.scale > 1` for
pinch-zoom. A follow-up preserves `#history` scroll-to-bottom for readers
who were at the bottom when `--vvh` shrank.

If this class of bug recurs elsewhere, the standing guidance is to vendor
a battle-tested library or port a published implementation verbatim —
this is a genuinely hard, well-known problem — not to hand-roll again.

## Consequences

- The `--vvh`-only earlier attempt felt sluggish and flapping precisely
  because it lacked the scroll-lock; with all three parts every
  mid-transition frame is stable.
- Verified in the iOS 26 Xcode Simulator driven from the shell
  (`xcrun simctl`), across focus / scroll-then-focus /
  blur-scroll-refocus / keyboard-dismiss. Still to confirm on the user's
  physical device, whose Safari may predate iOS 26; the fix depends only
  on `visualViewport` (iOS 13+), so it should hold.
- The plain flex layout remains correct whenever the keyboard is not
  involved.
