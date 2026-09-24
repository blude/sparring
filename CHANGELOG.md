# Changelog

Generated from git history. Grouped by commit date, newest first.

## 2026-09-17

- `3d2278b` style(ses): add subtle shadow to scenario list
- `f62e6fa` style(ses): suppress tap highlight on the summary row
- `fe4c87d` feat(ses): i18n the page title and heading
- `de54607` style(content-pages): increase footer padding-top to 2rem
- `6df27ac` style(ses): rename heading/title to "Evaluation Scenarios"
- `3b35ce0` style(content-pages): center header and footer site-wide
- `065488c` style(ses): center logo, heading and footer like start.php
- `b29ac6f` feat(ses): add a chevron before the title
- `34ee2e7` feat(ses): tapping the title toggles the description
- `798daf3` style(ses): shorten button label to "Start"
- `a086d09` style(ses): merge scenario cards into one list with dividers
- `5363c9c` style(ses): swap Start button border for a light red fill
- `879757c` style(ses): ghost-style the Start sparring button
- `11e6fd0` style(ses): move description below the title/button row
- `834662d` style(ses): right-align Start button, centered against card content
- `1fbf842` style(content-pages): drop RethinkSans from headings, use default font
- `7236e3e` style(public): match scenario Start button to start.php's #start-btn
- `1d443f6` feat(public): make scenario description collapsible
- `1e0e70b` fix(public): use explicit Start sparring button on scenario cards
- `3abff4f` feat(public): add scenario picker page for ses2-ses4
- `cf75449` style(config): remove comma from ses4 scenario
- `4a13130` feat(config): add opening prompts for sparring evaluation scenarios
- `aba3e67` refactor(config): change existing OPENING_PROMPTS keys to string type
- `6c72662` docs(spec): note curated-opener exemption from contribution char cap
- `9066ea2` fix(sparring): exempt curated QR openers from contribution char cap
- `f692406` fix(start): remove cookies link from footer

## 2026-09-15

- `d56e4a7` fix(start): update egg dialog message
- `6c6be9d` fix(start): update egg dialog dismiss label
- `7ffde29` fix(start): suppress mobile tap highlight on gloves
- `8580a56` feat(start): bounce+fade in egg dialog, fade out on dismiss
- `eefb12d` fix(start): tighten egg dialog copy
- `048899a` fix(start): match egg-dialog spacing to dojo's gate-card exactly
- `20324e8` fix(start): left-align egg-dialog copy, full-width centered dismiss
- `4e80187` fix(start): position particle-burst canvas as fixed overlay
- `639ce9d` feat(start): add glove tap easter egg

## 2026-09-14

- `c11d761` fix(arena): use 100dvh instead of 100vh for viewport height

## 2026-09-11

- `126b8cf` docs(spec): rebuild HTML for status/TBC changes
- `167cd00` docs(spec): resolve stadium profile TBC, mark SE-02 stable
- `7096bee` docs(spec): resolve SE-02 EX-01-3, no threshold needed
- `944c0ff` docs(spec): mark L1/L2 status stable, layout decision fully closed
- `48910d6` docs(spec): resolve SE-02 TBC-04 against real exhibition data
- `ed17576` docs(spec): propagate SE-02 TBC resolutions and resolve SC-04 concurrency
- `0ebdeaf` docs(spec): resolve SE-03 concurrency TBC, mark status stable
- `ae6bde4` docs(spec): resolve 5 of 6 SE-02 layout TBCs post-exhibition
- `0b32f04` docs(spec): mark SE-04 status stable, no open TBCs
- `a74924c` docs(spec): rebuild specs
- `1fb8495` fix(spec): exclude book.adoc from HTML build
- `27fd11b` docs(spec): move Business goals one level up
- `7e83509` docs(spec): add fourth level in toc this is done so certain deeply nested elements are shown
- `bb129c4` docs(spec): remove extraneous em-dashes
- `3b0a646` docs(spec): reduce sectnumlevels a lot of sub items in the table of contents already have an ID and number. adding section numbers only made that more confusing.
- `eb8e471` feat(spec): add PDF book build target
- `85d1514` docs(spec): remove stray chapter number

## 2026-09-10

- `0c4fd2e` docs(adr): start ADR log with 14 back-filled decisions

## 2026-09-07

- `e7a4ad0` docs: add start screen UI-evolution screenshot gallery
- `11e0814` docs: add dojo screen UI-evolution screenshot gallery

## 2026-09-06

- `12b5382` docs: update CHANGELOG through e2bb56e
- `e2bb56e` build(deps): sync composer.lock content-hash with composer.json
- `b6b7e3d` docs(export): note the --markdown dir target in README
- `876ff6d` feat(export): split --markdown into one file per session on a dir target
- `884fc56` fix(arena): make bottom scrim theme-aware
- `3de8b13` build(bump-changelog): version as <MAJOR>.<days-since-release>.0
- `0ba3dae` chore(release): 1.0.0

## 2026-09-04

- `f2a1bac` feat(config): add opening sparring scenarios
- `2722c9d` style(arena): widen reply-qr to 4rem
- `38625a1` feat(arena): fade overflow at bottom viewport edge with a white scrim
- `d0b398d` feat(export): --index mode, one scannable line per session
- `791eadb` docs(spec): model ?d= display profiles and count-first masonry
- `fb6cbb3` docs(spec): rebuild SE-04 html to match source
- `015e5cc` fix(arena): keep masonry columns count-balanced, not height-only
- `3409996` feat(arena): show 16 exchanges in the stadium display config
- `1acb525` feat(arena): named display configs via ?d=
- `b59b2b3` style(dojo): punch up the char-progress bump
- `f21739b` style(dojo): slant the char-progress bar with skewX
- `d9cbb8d` feat(dojo): play a charge sfx as the char-progress bar fills
- `58c9a3d` style(i18n): replace em dashes in dojo.js strings with sentence breaks
- `c96c2e1` style(css): enable hyphenation on content pages
- `c6de806` style(css): decrease line-height from headings
- `e37a2fb` style(dojo): lift avatar-alias color to match session-title shade
- `ff55b7d` docs(prompts): log authorship-deflection edge case, bump to 0.26.0
- `8266451` feat(prompts): deflect questions about who built Popov
- `2c71683` style(dojo): normalize avatar-btn line-height to browser default
- `79f3e60` style(dojo): pad session-title bottom to align with avatar row
- `ded964b` fix(dojo): keep the char-progress pulse in phase across all lit bars

## 2026-09-03

- `e171f18` docs(spec): reword SE-01 character-allowance display for the progress bar
- `b75acf3` feat(dojo): replace composer char counter with a segmented progress bar
- `2067397` style(dojo): rosé avatar disc, larger bottom-clipped emoji
- `5fed0ce` fix(dojo): keep the playbook dismissed once the composer is engaged
- `67174c8` style(dojo): enlarge header/history type, flush history scrollbar right
- `9b88139` feat(dojo): show bare number in composer char counter
- `9e06c07` feat(dojo): let history scroll behind the top bar
- `db1d9b2` feat(dojo): show visitor alias above the session title, drop the popover
- `f063a36` style(dojo): add subtle drop shadow to top-bar
- `cfedf70` style(dojo): tighten top-bar padding, adjust corner radii
- `7b2b813` style(dojo): remove dark circle background from avatar button
- `633ef77` style(dojo): stack the turn-limit choice, feedback as primary
- `201ab5c` feat(dojo): show the turn-limit choice when resuming a completed session
- `fd01325` feat(philosophy): replace stance photo with gloves and building images
- `24a192c` style(dojo): inset the red top bar with rounded corners
- `234a295` Revert "feat(dojo): edge-to-edge top bar, red iOS status/address bar"
- `fa033c1` feat(dojo): edge-to-edge top bar, red iOS status/address bar
- `31f9846` feat(dojo): brand-red top bar with light controls
- `56b124e` docs(spec): model session extension at the turn limit
- `715d788` feat(dojo): inline extend-or-feedback choice at the turn limit
- `d59bd86` feat(sparring): extend a session past the turn allowance
- `40aa3e1` docs: update CHANGELOG through 04d4b18
- `04d4b18` feat(dojo): restyle end-session button as transparent icon + "Finish" label
- `bfc3d6a` fix(dojo): skip end-of-session dialogs when nothing was sent
- `e8fd5e1` docs: engineering note on the dojo layout vs. the iOS software keyboard
- `2aeae37` fix(dojo): re-pin history to the latest message when the iOS keyboard opens
- `21d79d6` fix(dojo): keep composer and top bar in place with the iOS keyboard
- `233e03d` fix(dojo): lock document scroll so only #history scrolls
- `34a2f98` refactor(bin): single usage string in delete_session.php
- `6c04700` feat(bin): accept -y as alias for --confirm in destructive scripts
- `e2d8e9d` feat(bin): add delete_session.php to remove one session by id
- `60fd08e` feat(export): render session properties as YAML frontmatter in markdown
- `10fcb0a` feat(export): show session title in markdown transcript header
- `4ebea8a` feat(dojo): dismiss Playbook on composer focus, restore on blur

## 2026-09-01

- `f7aea4a` docs: update CHANGELOG through 7c5ba9d, version to 0.24.0
- `e196007` fix(bin): order multi-day CHANGELOG updates newest-first
- `7c5ba9d` feat(docs): timestamp generated diagram filenames
- `4a0ad8e` docs(diagrams): add docs/README.md documenting the generators
- `c04b5a0` chore(docs): group diagram files into per-artifact folders
- `cb9cdc7` docs(diagrams): rebuild commit punchcard as portrait, hours vertical
- `1f56420` fix(diagrams): drop paint-order text halo, use backing rects
- `67b7182` style(diagrams): recolour spec network by Sparring brand red
- `072ed96` docs(diagrams): translate spec network supporting text to German
- `5bac8f8` feat(docs): add force-directed spec network diagram
- `2e8bd0f` refactor(docs): extract shared spec-graph parser
- `d95ae53` feat(docs): add portrait feature-inventory word clouds
- `566659f` feat(docs): add spec requirement network diagram
- `05176db` docs: add feature inventory and commit-punchcard chart
- `80205d5` feat(docs): add landscape feature-inventory word clouds

## 2026-08-31

- `d425885` fix(meta): remove obsolete property
- `94b0847` feat(arena): tune iOS PWA status bar and splash background
- `4bfbcc9` feat(pilot): translate pilot seeds to German
- `aaaa3de` style(arena): nudge reply count slightly and tweak border add bg color border to better separate it from nearby bubble
- `34cf63a` feat(pilot): support reply_to link in pilot seeds

## 2026-08-30

- `723e7b5` feat(bin): add DDP Foundation Level handbook extractor
- `1672f9a` refactor(bin): extract shared PDF machinery to bin/inc/curriculum_pdf.py
- `2145e43` chore(assets): update share image with cleaner design
- `71a5f8d` refactor(config): drop square share image from ogTags
- `079be26` build(spec): rebuild L1 press release HTML
- `4551a7e` docs(spec): rewrite Future Press Release for plainer prose
- `04a3507` docs(spec): update Press Release date
- `6cb7b95` docs(spec): add line breaks to improve legibility

## 2026-08-29

- `1ba5ee4` docs(spec): change direction of diagram to LR
- `4ef1217` docs(credits): use Dr. Kim Lauenroth in acknowledgements
- `844963d` docs(credits): acknowledge Kim Lauenroth source material and logo fonts
- `36a098f` docs(i18n): humanize the philosophy page copy
- `fa526f7` docs(spec): humanize the index copy
- `f9c773c` docs(spec): make the index a readable entry point, not a link list
- `e5d5e85` docs(spec): add "What shapes a Sparring response" diagram to LX
- `1a68d08` style(content): increase border-radius of philosophy image
- `2e11b29` fix(img): update aspect ratio of philosophy image
- `e7e0a48` feat(content): add image to philosophy page
- `0e9c457` feat(pilot): add LLM-optimized titles to pilot transcripts

## 2026-08-28

- `fae0562` fix(curriculum): point CURRICULUM_DATA_DIR at digitaldesign-wiki subdir
- `5ff3a9a` feat(curriculum): add --skip page-range flag to Digitalentwurfslehre extractor
- `6693b28` chore: ignore __pycache__ anywhere, not just repo root
- `c950988` feat(curriculum): add Digitalentwurfslehre PDF extraction script
- `4c40bd1` style: fix indentation
- `4edc91c` style(footer): move copyright year onto legal-links line
- `167e9c2` docs: update CHANGELOG through 980d6a6
- `980d6a6` docs(prompts): backfill changelog heading for v9 prompt changes
- `b2cec80` refactor(prompts): move <voice> before <core_mechanism>, dedupe length rule
- `8499660` feat(prompts): handle genuine topic-diversion without filtering it
- `35ab4e5` feat(prompts): define frustration-escalation threshold in sparring.md
- `731cd0f` feat(prompts): add turn-limit endgame guidance to sparring.md
- `e2c1351` feat(prompts): add injection guardrail and voice examples to sparring.md
- `9274778` docs(prompts): backfill changelog headings for v8 prompt changes
- `fc30020` fix(bin): allow two in a row in grade_sparring's repetition check
- `a7a945a` feat(prompts): combine Socratic + Sparring per turn, rename tables
- `48ec92d` docs(spec): table of SE-04's sections in file read-order
- `ca681d6` docs(spec): drop drifting line/word count from C-03

## 2026-08-27

- `0df1b49` feat(prompts): remove boundary_objects section
- `2952768` feat(prompts): ground meta-question answer in martial-arts origin
- `99328b2` feat(prompts): vary how Popov elicits a response beyond '?'
- `4070c53` feat(prompts): place Popov inside the Sparring platform in identity
- `935e159` feat(prompts): rename AI identity from Sparring to Popov
- `e691875` feat(prompts): state hard 16-turn session limit in identity
- `97c013c` style(prompts): casual/sporty tone, em-dash ban, ellipsis pauses, vocatives
- `375626b` fix(config): correct favicon path in webAppTags()
- `cf97abf` docs: update CHANGELOG through 2885475
- `2885475` build(bin): rewrite bump_changelog as bash instead of PHP
- `dd6602f` build(bin): add bump_changelog.php to automate CHANGELOG/version bumps
- `5df198b` docs: update CHANGELOG through cb8610b, correct version to 0.19.0
- `cb8610b` chore(assets): move favicon.ico into assets/img
- `3aa29c4` perf(assets): optimize share-image jpgs
- `928a641` feat(seo): declare a second og:image, add width/height to both
- `abd1ce5` chore(assets): use share-image-sq.jpg for og:image
- `059511e` chore(assets): replace share-image.png with share-image.jpg
- `e4fc294` refactor(assets): move apple-touch-icon.png into assets/img/
- `aec7242` fix(assets): update webmanifest icon paths for moved apple-touch-icon
- `9848f91` refactor(assets): serve apple-touch-icon.png via fasset()
- `d421367` chore(assets): update apple-touch-icon image
- `b047777` chore(assets): update apple-touch-icon image
- `b2de822` refactor(ui): load gloves layers as CSS background-images
- `2ae855e` feat(ui): float the gloves layers independently
- `d3879ad` feat(ui): build gloves logo from layered images instead of one flattened asset
- `68ed254` fix(ui): align gloves image width to 112px (square)
- `db175df` feat(ui): replace start-screen gloves illustration with new webp

## 2026-08-26

- `f967bba` fix(bin): exclude .git from curriculum rsync
- `1c024cc` chore(fixtures): drop Verwandt/Verwendung im Buch sections
- `9d59e05` chore(fixtures): remove [[wikilinks]] from curriculum fixtures
- `5122858` docs: update CHANGELOG through 97f5267, bump version to 0.19.0
- `97f5267` feat(ui): disable tap-highlight on buttons and textareas
- `92079b3` refactor(css): use rem units in footer padding
- `4acc1dd` feat(dojo): fade gate-card dialogs in/out
- `7802711` docs(TODO): tick off completed tasks
- `63b587c` feat(ui): add hover/active states to buttons
- `80e31fb` feat(title): drop hard char cap, rely on prompt for brevity
- `f5749e0` feat(arena): show AI-generated title as session label, scenario fallback

## 2026-08-25

- `815c90d` refactor(css): uniform border-radius for dialogs and interstitials
- `c48e322` fix(i18n): simplify retention messages
- `28c6a17` fix(css): adjust eval heading margin
- `a65720c` style(dojo): match #eval-success-message text style to #confirm-description
- `9ef769d` feat(dojo): custom confirm dialog for "End this session?"
- `d46f4b5` style(dojo): center #retention and #eval-dialog instead of bottom-anchoring
- `b905df6` refactor(css): consolidate playbook tape and adjust color
- `5790948` style(dojo): subtle drop shadow on #retention and #eval-dialog
- `66ceb06` style(dojo): overlay #retention the same way as #eval-dialog
- `f3ab915` style(dojo): 0.75rem gap between eval dialog and viewport bottom edge
- `96194f5` style(dojo): bottom-align the eval dialog overlay
- `585445e` style(dojo): overlay the eval dialog instead of pushing content
- `910c7f2` fix(css): adjust success message spacing and alignment
- `e2cb892` fix(dojo): show the confirmation screen, not a redirect, on a repeat End session
- `035dc07` fix(dojo): don't lock out the eval dialog on a reload before completing it
- `6571b43` feat(dojo): Skip also confirms with the two-action screen
- `7ce35bc` style(dojo): glove-icon rating widget for eval questions
- `dc2f8b3` style(dojo): match "Go back to start" to the Skip button
- `9112ceb` fix(dojo): keep the evaluation confirmation on the dojo screen
- `32c0ba5` fix(dojo): keep evaluation feedback from being lost on submit
- `2099aae` feat(dojo): add end-of-session evaluation dialog
- `64a565c` feat(error): add shrug kaomoji to error pages
- `a129bfb` docs: update CHANGELOG through 1f2b2f6, bump version to 0.17.0
- `1f2b2f6` build(deploy): add deploy_curriculum.sh script

## 2026-08-24

- `1e954cb` Store: note the exchange-pair-per-row schema decision and its scope bet (#1)
- `68cdcda` build(spec): share one stylesheet instead of embedding it per page
- `0355d13` build(spec): drop Asciidoctor's default footer
- `976b1e0` docs(spec): add gate-logic flowchart to TF-01
- `31da67a` docs(spec): add sequence diagram to SSc-01
- `2f6207f` docs(spec): add technology-stack diagram to LX
- `4267848` build(spec): render mermaid diagrams client-side
- `f172dff` docs(spec): surface curriculum grounding in L2's architecture diagram
- `3fd4181` docs(spec): add detailed architecture diagram to LX
- `7485de6` docs(deploy): trim post-deploy env note, keep the disambiguation
- `9695c58` feat(dojo): hide playbook by default, show only for new/empty session
- `d105f31` docs(deploy): document curriculum corpus seed as separate post-deploy step
- `399b7c2` docs: reorganize TODO.md by priority, consolidate Done section
- `09201e4` docs(prompts): backfill changelog headings for v7 prompt changes
- `4864db8` chore: merge pull request #3 from blude/claude/remove-mermaid-support-mholi1
- `5ca1dcb` chore: merge pull request #2 from blude/claude/rag-curriculum-knowledge-yiwl97
- `657ddd5` feat: wire curriculum retrieval into turn-1 generation (G-05)
- `509316e` fix(tests): correct German grammar in case 1's probe sentence
- `1bac29a` docs: track curriculum retrieval follow-ups in TODO.md
- `078a539` feat(store): mechanical retrieval-quality fixes for curriculum search
- `30ac702` feat(bin): add probe_curriculum.php for manual retrieval checks
- `2588018` feat(bin): add clear_curriculum.php to empty curriculum_chunks
- `27c77fc` feat(import_curriculum): skip typ: stub pages during ingestion
- `f8da0c7` Fix Copilot review findings: FTS5 quoting + importer failure paths

## 2026-08-23

- `72a1b44` perf: remove mermaid diagram rendering support
- `75f00bf` Add curriculum FTS5 ingestion + search (poor woman's RAG, step 1)
- `a2f6c73` fix: fasset() to load open graph img

## 2026-08-22

- `9ed4d90` chore: extra line break
- `0591bcc` refactor: add wrapper tag to locale switcher
- `b4ce37c` fix(ascii): change font to avoid character scaping
- `7c7dd86` perf: defer JS includes in arena and dojo pages
- `ecc1ff0` docs: update CHANGELOG through 297fd82
- `297fd82` chore(config): move juiciness flags up
- `d84cdff` chore(config): move environment and error handling down
- `3ec732f` chore(config): move LLM config up
- `b27d612` refactor(config): extract web app meta tags
- `d1af924` feat: add silly ASCII art banner on every page
- `0c82574` fix: unify meta tag inclusion
- `60c3881` docs: update CHANGELOG through 2408255
- `2408255` feat(dojo): rotate thinking-status message per turn
- `b913544` fix(i18n): unify thinking status message
- `6e39d8e` chore: bump version to 0.14.0
- `ec8e9d6` docs: update CHANGELOG through a867370
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
