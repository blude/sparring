# Changelog

Generated from git history. Grouped by commit date, newest first.

## 2026-08-22

- `a867370` fix(css): left align locale swicher on content pages
- `5533ed3` perf(js): minify qrcode generator
- `fa51fc4` refactor(arena): merge window-var scripts into one <script> tag
- `b5cc174` refactor(assets): move vendored libs to assets/vendor/
- `abd9967` refactor(content-pages): extract shared style block to content-page.css
- `43f3562` style(content-pages): reuse start page's footer/locale-switcher CSS
- `ac40e81` refactor(css): add drop shadow to locale switcher
- `88ac8dd` feat(credits): update exhibition opening hours
- `99f50ee` refactor(config): wrap page-chrome helpers in a section header
- `f1f57ec` docs(credits): add qrcode-generator to acknowledgments
- `5671c65` feat(content-pages): add header/footer chrome to philosophy/privacy/terms/credits
- `96fa9bf` docs(TODO): tick off recently implemented to dos
- `a543d1d` feat(config): raise TURN_ALLOWANCE to 16 exchanges per session
- `5ac7ee2` refactor(start): tweak design of locale switcher - swap background and selected color - back container pill-shaped
- `8d99765` feat(arena): add session count to header stat
- `7e99c7a` fix(arena): increase clock font size
- `6e8cb2b` fix(arena): drop trailing period from German month abbreviation
- `69d1358` fix(arena): correct invalid align-items value
- `45e282b` fix(arena): 24-hour clock format
- `1b056ed` fix(arena): align header items to the top
- `ce74b3e` test(store): de-brittle countExchanges assertion
- `0e83ef7` fix(arena): address advisor findings on header stats commit
- `b746df4` feat(arena): header stats — live clock + total exchange count
- `54e4007` refactor(arena): gradient-top as CSS background, not img
- `baf9f63` refactor(arena): rename gradient-top
- `6c25335` feat(arena): add gradient-top color wash behind logo
- `0bd6348` perf(images): compress images to webp
- `de31dbb` chore(assets): remove unused logo SVGs
- `bcd2604` fix(pwa): add standard mobile-web-app-capable meta tag
- `d3ae9ac` refactor(arena): swap logo for sparring wordmark, dot-grid background
- `543be9d` refactor(arena): flip alignment of messages
- `4d62ddf` refactor(arena): nest visitor-name inside contribution, mirroring response-text
- `123caa1` chore(git): merge worktree-qr-quote-reply into develop
- `1fe4b8d` style(arena): brand-red glove icon, match reply-qr's corner offset
- `59b8d3d` refactor(arena): force QR code links opening in new tab
- `19570eb` refactor(arena): adjust positioning of QR Code
- `6c5854e` feat(arena): move reply QR to a top-right corner badge on the response
- `3ffae10` feat(arena): show reply count as a glove-icon reaction pill
- `5d9e036` refactor(dojo): rename reply query param to r for consistency

## 2026-08-21

- `c1404fa` fix(i18n): route the quote's Replying to label through t() instead of a literal
- `4d586fc` feat(dojo): label the quote as 'Replying to:' for the model, not just the visitor
- `c71aec7` fix(dojo): hide quote chip instantly on send, persist quote in the bubble
- `3f0dcbc` fix(dojo): #reply-quote's display:flex overrode the hidden attribute
- `f866f4a` feat(arena): QR quote-reply from wall messages to dojo
- `855504b` build(assets): vendor qrcode-generator for client-side QR rendering
- `34acfb9` fix(routing): serve trailing-slash routes and use absolute asset paths
- `335fdc5` refactor(css): move font declaration to start of file
- `48c320f` fix(design): adopt default system-ui font for most text
- `9e32511` feat: add trans flag in footer
- `b71832c` fix(typo): author name correction
- `f0d08ef` refactor(content): add cookie privacy info
- `786e5c4` docs(privacy): add cookie usage paragraph
- `29471cb` refactor(design): make playbook note more subtle
- `7b3c383` refactor: extract start.php inline CSS to assets/start.css
- `ba2858b` refactor: swap philosophy link location
- `94bb017` refactor: make footer text lighter
- `d3eacf7` refactor(i18n): simplify legal line
- `06fd122` refactor: add copyright {year}
- `a77eda9` refactor: fix design of localeSwitcher

## 2026-08-20

- `910caa9` refactor(input): remove localeSwitcher from top bar
- `4aa2c19` docs(todo): remove stale bug todo item
- `f0fbdae` docs(todo): tick off recently completed to dos
- `78b1828` docs(spec): add line breaks to improve legibility
- `c25f82b` docs: update CHANGELOG through ecce8af
- `ecce8af` refactor(identity): use roman numerals as suffix
- `154c232` perf(llm): enable prompt caching on the Sparring system prompt
- `c05bd78` docs(spec): add SE-04 constraints on context-window sharing and XML structuring
- `5941863` docs: fix broken sentence in CLAUDE.md's spec/ section
- `c886401` docs(spec): scaffold SE-04, the system prompt, as a first-class element
- `7296fd7` docs(spec): promote requirements to sub-sections
- `459feac` docs(spec): flatten sections of L2 system design
- `5065b31` docs(spec): resolve stale TBCs against shipped config.php values
- `368fc30` docs(spec): remove stray section number
- `02cf81b` docs(spec): give entities, interfaces, technical functions IDs consistent with the rest
- `3d24016` docs(spec): give goals, quality requirements, and constraints consistent IDs
- `8f55745` docs(spec): drop em-dash between ID and title in definition lists
- `87d6649` docs(asciidoc): remove stray section number
- `e807d3c` docs(spec): fix element-name duplication, restructure SE-04
- `2cf7206` docs(spec): convert standalone meta-notes into real admonitions
- `2b923b6` docs(spec): single space between ID and title in link labels
- `89cc23f` docs(spec): use colon, not em-dash, between ID and title in link labels
- `108996e` docs(spec): show requirement/element names alongside IDs in link labels
- `82850a9` docs(asciidoc): add navigation to glossary page too
- `060e1c8` docs(asciidoc): build spec website
- `e35e55d` docs(asciidoc): make specs easier to navigate
- `69b8938` docs(spec): tweak glossary table column size
- `a05ace4` fix(router): redirect /spec and /spec/ to spec/index.html
- `179c170` build(spec): add bin/build_spec.sh to rebuild public/spec/
- `dd0fc73` docs(spec): add index linking all design docs
- `babf672` docs(spec): publish HTML export to public/spec/
- `72e5ab4` docs(specs): add version, author and date on the header
- `0013987` docs(spec): add cross-document references and fill missing links
- `ecbd9fe` docs(CLAUDE): remove reference to StrictDoc
- `5bbdbf1` chore(design): priorize rasterized logo
- `46da3de` chore(design): bring gloves and logotype close together
- `a70742b` chore(branding): update log with fun style and simplify logotype
- `656d2ae` chore(img): update apple-touch-icon
- `42db054` chore(i18n): adjust translation for spar! in title card
- `5a81441` chore(lang-switcher): hide lang switcher from start page
- `0f6c67e` docs(todo): tick off recent to dos
- `25dc577` docs(spec): add German locale switching and reply-language matching to SE-04/SE-02
- `f90f7a1` feat(prompts): reply/title in the visitor's own message language
- `afd18da` feat(i18n): wire German translations into every page and dialog
- `9e07c18` feat(i18n): add German locale infrastructure

## 2026-08-19

- `6c26c96` test(eval): add multi-turn evaluation suite for prompts/sparring.md
- `3e2aadd` refactor(dojo): rename opening scenario query param to o, key by number
- `a906a7f` docs(spec): fix typos across spec docs
- `bad887e` feat(web): add installable manifest to dojo and arena
- `d45728e` feat(export): add --csv and --markdown formats
- `cc0d467` feat(export): add --consented-only flag
- `aaa804d` fix(export): group --jsonl by session, not by exchange
- `4a56eea` fix(export): shape --jsonl as OpenAI fine-tuning format
- `53d966c` docs(spec): add prune_orphaned_sessions.php to C-04
- `694fb6b` feat(export): add --jsonl and --session flags
- `836f7eb` feat: add fallback error pages for 404/500
- `47c4dde` docs: update CHANGELOG through d38afcc
- `d38afcc` docs: point README setup at tests/run.sh instead of stale two-test list
- `eedc7bb` chore: green checkmark next to ok in run.sh
- `489512f` chore: show [step/total] progress in run.sh
- `656d52f` chore: standardize smoke test success messages to "<name>: ok"
- `08e0799` docs: note run.sh's per-process rationale, list all 10 smoke tests
- `5f70744` test: add smoke_domain.php for the small pure PHP helpers
- `1410bc4` test: expose durationMs/isChord on SparringSfx, add smoke_sfx.js
- `6b50a4f` chore: add tests/run.sh to run every smoke test in one shot
- `88743e5` docs: list all smoke test files in CLAUDE.md
- `d4ee28f` refactor: extract AnthropicLlmClient::classifyGenerationFailure
- `2a11931` refactor: injectable $now on checkAndIncrementRateLimit/isExpired
- `f8be6e1` refactor: extract pure decision tables from dojo.js and arena.js
- `a621993` test: add smoke_sparring.php for processTurn's 7-gate pipeline
- `9ceb105` rename: session_state.php to session-state.php for kebab-case consistency
- `c5ceaf5` rename: input/display routes to dojo/arena, api/display to recent-exchanges

## 2026-08-18

- `b9fe76b` feat: add Alpha Preview notice to start page
- `cd7f8a2` chore: change dash character
- `3a3e15a` chore: rename variable
- `3984cd2` chore: alignm code comments
- `bf13e1c` feat: replace base-word+suffix alias scheme with jiraiya name generator
- `77364f1` fix: identity.js seed() misparses Crockford Base32 sessionId as hex
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
