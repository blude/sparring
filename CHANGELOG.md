# Changelog

Generated from git history. Grouped by commit date, newest first.

## 2026-08-18

- `11dd9e7` fix: clear composer draft from sessionStorage on end session
- `73583bd` prompt: add edge case rule for meta questions
- `d07b8fd` Merge branch 'feat/shorter-session-id' into develop
- `82eae82` feat: shorten session ID to 8-char Crockford Base32
- `c7c803e` feat: switchable LLM provider (Anthropic, OpenAI, LM Studio)

## 2026-08-17

- `9dd5534` style add small padding on top of content area
- `9436941` style: slight increase composer footer font size
- `bc96117` feat: persist composer draft in sessionStorage across reload
- `cb7d847` prompt: reorganize sections
- `d46f283` prompt: considerably expand the system instructions defines richer identity with examples, vocabullary, voice...
- `394cd27` style: subtly nudge subtitle to the right
- `5f1bc3c` docs: add sparring.md changelog and update workflow note
- `d4e44e6` docs: add changelog covering full repo history

## 2026-08-16

- `f032316` fix: set explicit composer.json root version
- `3cd92c1` feat: render mermaid diagrams in sparring partner responses
- `996f1f8` style: reduce padding and margin around .content section
- `ee57259` chore: add description meta tag for better SEO
- `fd37a48` prompt: organize sparring prompt into XML tags
- `a9ee0f9` chore: add full size share image
- `de765bb` style: remove gloves rotation
- `f3d0a51` feat: add Open Graph/Twitter card meta tags to public pages
- `cc5b852` feat: add prod deploy workflow via rsync+SSH
- `9d01fa5` chore: move design docs/ to spec/, update references (rationale: it's the proper location)
- `e32ddbd` chore: ignore .DS_Store files
- `ddeb5ba` chore: add image alt text
- `6117115` chore: change comment to PHP block to hide it from final output
- `9fc8bfa` chore: optimize image size
- `5c03df9` fix: route every request through index.php, load config.php once
- `44eccbe` fix: explicitly import favicon.ico to avoid 404 errors
- `849660b` style: bake in glove rotation
- `7068e69` style: shimmer #session-title only while /api/title is in flight
- `644e336` feat: async LLM session title generation with trim fallback

## 2026-08-15

- `40fee23` style: add padding to bottom of history, prevents messages being covered by the composer scrim when scrolled to bottom
- `92dee1d` feat: optimistic visitor turn in chat history on submit
- `b651fa0` style: remove italic
- `0210a4f` feat: switch classifier back to haiku now off-exercise category is gone
- `94b6aa1` feat: cache-bust CSS/JS asset URLs with fasset() helper
- `88a5cd0` style: adjust playbook shape heights
- `e39080b` chore: update intro copywriting
- `44606d6` chore: drop redundant env-source comment in config.php
- `2ec9397` chore: drop stale .envrc entry from .gitignore
- `9453dfe` feat: load ANTHROPIC_API_KEY from .env, drop direnv
- `b3e5b76` feat: pretty URLs via front-controller router, Valet support
- `e14711f` feat: let composer textarea grow up to 5 lines
- `71de4fd` chore: add philosophy page
- `239a5fe` chore: add repo license and acknowledgments
- `5c2f541` tweak placeholder text
- `f500d92` feat: replace off-exercise guardrail with targets-real-person
- `67e0efd` feat: add send on enter keypress

## 2026-08-14

- `d9ac3e4` fix: particle burst rendering behind title card
- `0376078` feat: splashier particle burst for title card reveal
- `658b8b5` feat: particle burst on SPAR! title card reveal
- `9cfe34a` style: invert consent dialog to dark so it pops against the page
- `07513a0` fix: composer hidden under Safari address bar on iOS
- `6af6ba7` adjust copywriting of consent dialog
- `87031a2` adjust consent dialog text styling
- `488555e` add muted visited link color
- `828b121` restructure participation consent dialog
- `a2f37b6` update content on credits, privacy, terms pages
- `b06e1cc` fix: #playbook absolute not fixed, avoids iOS overscroll rubber-band
- `61de139` fix: disable extended thinking on both LLM calls
- `a5471e5` make dotted background a tiny little more visible, also add it to the input screen
- `3253df3` reimplement playbook bullets with css only
- `27dfd45` fix: higher resolution touch icon
- `5984d94` check complete to do items off
- `08b71c1` set Helvetica Neue as default base font
- `3757cd6` update design of title card with new custom font
- `e9fcdd8` flip icons horizontally
- `7219ef0` add basic app icons
- `0c37b4c` adjust spacing of index view, manually removed minimum height set to viewport height
- `4071cf2` fix: presented-by line back in normal flow, was absolute-positioned and overlapping branding on short viewports
- `5f332f6` fix: footer gets its own 32px padding per UI-1 Start spec, drop the redundant divider bottom margin
- `642c426` fix: remove z-index from playbook-body to avoid overlap with composer-row

## 2026-08-13

- `0798135` Merge branch 'worktree-update-the-public-index-php-design-jiggly-walrus' into develop
- `7694c7d` style: playbook backing shapes as skewed divs, drop the two SVG assets
- `65c05b1` fix: playbook bullet fill is #666 gray now, not brand red — checked the actual ellipse asset, mockup changed it
- `7632cf8` add simple glossary to specs
- `8d87045` style: header order flipped back — avatar leading, not trailing
- `f8f005f` fix: avatar popover anchored to the wrong side after header restructure
- `2e4d11c` style: transition border-color/box-shadow on #composer-row, respect prefers-reduced-motion
- `3faf061` fix: implement the Textarea component's actual Normal/Focused/Disabled states
- `7b0770d` fix: bubble radius 12px->14px, composer text color 333 not inherited black
- `0730071` fix: avatar button colors/size/border were off spec too
- `f2ef695` fix: style #new-session-btn per Figma (was unstyled, browser-default button)
- `17505fb` style: bump send icon to 40px, matches Figma's icon-in-44px-button spec
- `f250126` fix: composer box never actually got its Figma styling, Playbook dimmed per updated mockup
- `35707ce` fix: real Figma assets for Playbook bullets/shadow, correct glove icon, font stack
- `7139ac6` fix: stack ToS dialog above the Playbook card, not under it
- `bc20c98` feat: redesign input.php per the "UI-2 Input" Figma family
- `c5b2585` style: drop vertical padding on .content, horizontal only
- `fb34d25` fix: even out 32px gap around the divider
- `52b5d82` style: drop the gradient wash
- `7aa4afa` style: add presented-by strip and footer credit line, per updated mockup
- `85ece2f` fix: pad the 100lvh page height so it never undershoots collapsed-bar height
- `588419a` style: let page height (not fixed positioning) carry gradient past the fold
- `44eb1c3` style: move gradient wash from top to bottom, per updated mockup
- `0042b1f` fix: let index.php background bleed under the safe-area inset
- `90c248c` style: implement UI-1 Start Figma mockup on index.php
- `b4e3696` style: collapse SFX Debug format help into <details>
- `8b5cdf0` style: tweak SFX Debug layout — section headings, help text placement
- `fbfcf04` feat: add titleCard button + rest/chord support to SFX Debug chain
- `66084e5` feat: two-column layout for SFX Debug page
- `1ae442b` feat: add --help/-h to bin/*.php scripts
- `230a763` feat: rename New session to End session, resume EX-03-2 exception
- `50bc548` feat: add bin/prune_orphaned_sessions.php

## 2026-08-12

- `7cc60b8` feat: rework titleCard sfx to match 3-phase, 3.1s title card
- `57c6f90` feat: three-word title card, same-spot slide/grow animation
- `095b704` ignore workspace-local folder with working files
- `8ecb78c` feat: bright uplifting sound chain for title card, with polyphony
- `54b1af4` feat: tune sfx presets, rename success->parry, error->fumble
- `3ea8e60` feat: swap sfx engine to ZzFX, add session-end note sequence
- `b172cbe` feat: add sfx-debug.php for live-tuning sound effect params
- `092a292` feat: add JUICYNESS animations, sound, and on/off toggles
- `885aba2` add two more animation types
- `5a56421` expand juiciness and add evaluation at end of session
- `b3a6472` expand on juiciness bit
- `514a5cc` add juiciness to dos

## 2026-08-10

- `efbe5cd` tweak to dos
- `6d75c19` separate colors in dark and light modes
- `3e93e3e` add to dos for admin screen
- `e150acd` feat(input): send button icon as CSS mask, glove replaces arrow
- `63bf8cb` Move Display priority items to Done
- `1de2b6b` feat(display): 2-column masonry layout, wordmark header, brand-red accent
- `ed310d9` style(display): render exchanges as stacked chat bubbles
- `f4f0cd7` add priority display to dos

## 2026-08-07

- `73178c7` add new to dos
- `60495b2` Quick-win batch: TODO.md low-hanging fruit
- `c69d676` add to do list with new features, bug fixes and improvements
- `e0f3e84` swap page title order
- `599efbc` add basic credits
- `2638bfd` Confirm X-Forwarded-For trust assumption against live EasyEngine site
- `2c24f6a` Add QR-11 generic error responses and C-06 prompt-injection guard
- `1e37d98` Fix X-Forwarded-For parsing to use last hop, not first
- `4e65437` go back to .envrc file to store secrets
- `d4f7fc5` move secrets to .env file
- `dc1b38c` lowercase middle base word, add base words, suffixes and avatars
- `b0f941a` Populate CLAUDE.md with stack, conventions, docs/ modeling rules
- `2ff53df` Reconcile design docs with visitor identity, debug mode, DB tooling
- `4fa311d` Surface debug diagnostics; add DB reset/backup scripts
- `fe221dd` Add index/landing screen and credits page
- `d5c335f` Show generated visitor names + debug tags on the display wall
- `fdc0376` Add input screen header: visitor identity, new-session, debug panel
- `f91a117` docs: reconcile with reject-and-edit moderation and three-part consent
- `dab5339` Replace binary retention prompt with three-checkbox consent form
- `8b8aa93` Revert #history:empty layout fix per user request

## 2026-08-06

- `54e6d7c` Fix moderation over-flagging + retention dialog dead space
- `c4010c3` POC: backend service, input/display clients, LLM integration, pilot seed/export
- `3255832` initial CLAUDE.md
- `5945d00` add system realization reqs
- `a00ca7e` update requirements to prevent unwanted usage
- `513fe49` initial commit with docs
