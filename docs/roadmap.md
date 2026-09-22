# Roadmap: from exhibition MVP to production

Status: **proposal**, not a commitment. Written 2026-09-22 against v1.7.0.

Possible refactoring paths for taking Sparring from a thesis exhibition
piece to a production release with accounts, real RAG and a roster of
sparring personas, while making the app more maintainable, stable and user
friendly.

This is a planning note, not a design record. When a step here is taken,
its technical decision gets an ADR in `docs/adr/` and any behavioural
change gets modelled in `spec/`, as usual. Items that already exist in
`TODO.md` are referenced, not duplicated.

## Starting point

Sparring is in a better position to grow than most exhibition MVPs:

- Domain logic is already kept apart from the pages (`Sparring`, `Store`,
  `LlmClientInterface`).
- The spec and ADRs record why each decision was made.
- `# ponytail:` comments mark exactly where the code took shortcuts.

Most of this roadmap follows from those notes and from the ADRs that a
production release would replace: **0001** (no framework), **0005**
(polling), **0006** (identity in the URL) and **0009** (FTS5 retrieval).

## Phase 0: Foundations

These make every later step cheaper. None of them change what visitors see.

1. **Split `config.php`.** It mixes constants, the LLM factory, i18n
   (`t()`, `resolve_locale()`), view helpers (`pageHeader()`, `ogTags()`)
   and error rendering. Move these into `config/`, `src/I18n/`,
   `src/View/` and `src/Http/`, and add PSR-4 autoloading in
   `composer.json`. That also removes the `require` chains repeated at the
   top of every `public/api/*.php`.
2. **Turn the API into controllers.** `public/index.php` already has a
   route table. Map `/api/*` to small handler classes that share a
   Request/Response helper and one JSON error format. The status-to-HTTP
   mapping in `public/api/contribute.php` becomes a reusable pattern
   instead of a copy per endpoint.
3. **Version the database migrations.** `Store::migrate()` adds columns
   with an ALTER-if-missing check, which won't scale to many schema
   changes. Use numbered migration files tracked with
   `PRAGMA user_version`.
4. **Replace `exchanges` with a `messages` table.** The ponytail comment in
   `Store::migrate()` already names this as the exit path. One row per
   message, with a role, unlocks most of the `TODO.md` list: regenerate,
   rate good/bad, streaming and timestamps. It also gives each message a
   place for metadata: model, prompt version, tokens, latency, persona, and
   which curriculum sources were retrieved.
5. **Split `Store` into repositories.** For example `SessionRepository`,
   `MessageRepository`, `CurriculumIndex`, `RateLimitStore`,
   `EvaluationRepository`. This is also the seam that makes a later move to
   Postgres a contained change.
6. **Add CI and static analysis.** Run `tests/run.sh` and `php -l` in
   GitHub Actions. Add PHPStan as a dev-only dependency, which fits the
   no-runtime-dependency convention.
7. **Add observability.** Structured logs with a request ID, and token and
   cost accounting per turn. Cost data is needed before there are real
   users.

## Phase 1: Authentication and identity

Supersedes ADR-0006.

- **Keep anonymous use, add optional accounts.** Walk-up anonymous sessions
  stay for exhibitions. A signed-in user can claim a session
  (`sessions.user_id` is nullable). Supporting both lets one codebase serve
  an exhibition and a classroom.
- **Sign-in method:** magic links or passkeys (WebAuthn) for low friction.
  For students, OIDC/SAML SSO with the university identity provider saves
  them another password.
- **Roles:** visitor, student, instructor, operator. An instructor role
  opens up course features such as assigning a scenario or reviewing a
  class's spars. Over time, admin screens replace the ops scripts in
  `bin/` (`delete_session`, `export`, `prune_orphaned_sessions`).
- **Follow-on work:** cookies bring CSRF protection and a tighter CSP.
  Consent moves from per-session to per-user, with a per-session override
  for projection onto the wall. The `TODO.md` item "export my
  conversation" and self-service deletion become user-facing GDPR
  features.
- **Rate limits and budgets move from per-IP to per-user,** with IP limits
  kept for anonymous users.

## Phase 2: A roster of sparring personas

Today `prompts/sparring.md` is a single file, modelled in the spec as SE-04.

- **Persona registry:** one folder per persona, e.g.
  `prompts/personas/<slug>/`, holding `persona.md` plus metadata: name,
  avatar, stance, tone, model, curriculum collection, and its own eval
  scenarios.
- **Split the prompt into a shared base and a persona overlay.** Safety
  rules, output format and the Socratic moves stay in a shared base. Each
  persona adds only its character. With Anthropic prompt caching, base and
  persona become two cached blocks, so the cache discount protected today
  in `AnthropicLlmClient::buildSystemBlocks()` survives across personas.
- **Pass a context object to the LLM client.** Replace
  `generateResponse($prior, $new, $grounding)` with something like
  `generateResponse(TurnContext $ctx)` before the parameter list grows
  further. Add `sessions.persona_id`.
- **Version prompts.** Store a hash of the prompt on each message, so
  quality in the evals and in user ratings can be traced to specific prompt
  versions. `prompts/CHANGELOG.md` already covers the human-readable side.
- **Evals per persona.** Parameterize `evals/sparring/scenarios` by persona
  and run them nightly or on changes to prompt files. Too expensive for
  every push, but the only guard against a persona drifting into agreeing
  with the visitor.
- **UI:** a fighter-select screen fits the arcade look. The arena shows who
  is sparring whom. Later, a tag-team or debate mode where two personas
  argue.

## Phase 3: Real RAG

Supersedes ADR-0009. The current setup is FTS5 with BM25, grounds only the
first turn, and has the known gaps listed in `TODO.md`: hub pages
outranking focused pages, English loanwords matching German titles, and no
language detection.

1. **Build a golden set first.** Extend
   `tests/smoke_curriculum_retrieval.php` into a query/expected-page set
   with recall@k scores. Without it there is no way to tell whether
   embeddings actually help.
2. **Chunk by heading instead of by whole page.** This fixes the hub-page
   problem more cheaply than any model change.
3. **Hybrid retrieval.** Keep FTS5 and add embeddings behind an
   `EmbeddingClientInterface`, mirroring ADR-0008. Store vectors in SQLite
   with `sqlite-vec`, or in `pgvector` after a move to Postgres. Merge the
   two result lists with reciprocal rank fusion. Detect the language before
   searching.
4. **Let the model decide when to search.** Expose `search_curriculum` as a
   tool the model can call on any turn, not only turn 1. This has to fit
   within `GENERATION_TIMEOUT_SECONDS`.
5. **Citations.** Record the retrieved sources on each message and show
   them as source chips in the dojo. This supports the spec's stance that
   excerpts are never asserted as fact.
6. **Ingestion:** make `bin/import_curriculum.php` incremental using
   content hashes, and use named collections so each persona can have its
   own corpus.

## Phase 4: Real-time UX and the frontend

- **Streaming** (deferred in `TODO.md`, supersedes ADR-0005 for the phone).
  The least disruptive route is FrankenPHP: it stays PHP, supports SSE
  natively, and has a worker mode. The wall can keep polling.
- **Parallel moderation.** Moderation and generation run one after the
  other, so a visitor waits for two LLM calls. Running the classification
  alongside generation, and discarding the response if it's flagged, cuts
  perceived latency at the cost of occasionally wasted generation tokens.
- **Split `public/assets/dojo.js` into native ES modules.**
  `<script type="module">` needs no build step, so ADR-0001 still holds.
  Give the chat's states (consent, composing, thinking, rejected,
  turn-limit) an explicit state machine; right now they're spread across
  handlers.
- **`TODO.md` items this unlocks:** message actions (copy, rate,
  regenerate), timestamps, JSON export, and "my spars" history once
  accounts exist.
- **Accessibility audit:** check that the juicy effects respect
  `prefers-reduced-motion`, and test screen readers and keyboard navigation
  in the chat.

## Phase 5: Stability and operations

- **SQLite is not the bottleneck yet.** In WAL mode it handles more load
  than expected. The first real limit is PHP-FPM workers held open for up
  to 20 seconds on LLM calls. Move to Postgres only when multiple app
  servers or pgvector are needed. The repository split in Phase 0 keeps
  that move cheap.
- **Backups:** add Litestream for continuous SQLite replication, alongside
  `bin/backup_db.php`.
- **Deployment:** move from rsync to EasyEngine (`bin/deploy.sh`) toward a
  Docker image built in CI, with a health endpoint and error tracking.
- **Cost controls:** token budgets per user and per persona.
  `Sparring::extendSession()` is unmetered (see its ponytail note) and
  should get a cap once real users arrive.

## Open decision: stay framework-free or not

Accounts, roles, migrations, queues, an admin UI and SSE are exactly what a
framework provides.

- **Stay vanilla** and add a few focused libraries (a router, PSR-7, a
  migrations tool). Keeps the project's character, but means maintaining
  its own auth and admin code.
- **Adopt Laravel.** The project already runs under Valet, and Laravel
  brings auth, OIDC SSO, migrations, queues, websockets and an admin panel.
  The domain classes (`Sparring`, the LLM clients, retrieval) have no
  framework dependencies, so they would mostly port as they are.

**Recommendation:** do Phases 0 and 2 in vanilla PHP, since personas and a
cleaner structure don't need a framework. Make the framework decision at
the start of Phase 1 as an explicit ADR that supersedes ADR-0001, because
authentication is where a hand-rolled approach starts to cost more than it
saves. Migrate gradually: API endpoints first, then the pages.

## Spec implications

Production is a new solution concept, not a feature added to the
exhibition one: the L0 and L1 goals (`BG-`, `SG-`) are about the
exhibition. A spec v2 would add use cases for sign-in, persona selection
and history, and keep the exhibition as one deployment mode of it.

## Suggested order

1. Phase 0 (foundations)
2. Phase 2 (personas: visible payoff, little risk)
3. Phase 3 (RAG, evals first)
4. Phase 1 (auth and the framework decision)
5. Phases 4 and 5 (streaming, ops hardening)

## What to avoid

- Adding a vector database before there are retrieval evals.
- Splitting into microservices.
- Rewriting the frontend in a SPA framework when native modules solve the
  maintainability problem.
