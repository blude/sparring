<?php
declare(strict_types=1);

require_once __DIR__ . '/scenario.php';

/**
 * SE-03's core: every rule the installation runs on lives here (AP-01), so no
 * client can carry a copy of a limit it doesn't independently re-enforce.
 *
 * $llm and $rateLimiter are optional so the display path (assembleDisplayMaterial)
 * can run without an API key configured — only processTurn needs both.
 */
final class Sparring
{
    public function __construct(
        private Store $store,
        private ?LlmClientInterface $llm = null,
        private ?RateLimiter $rateLimiter = null,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Session state
    |--------------------------------------------------------------------------
    */

    /**
     * TI-01/TI-03: "unknown or expired" is treated identically everywhere it's checked.
     * $now defaults to time() — injectable so a test can check the TTL boundary
     * deterministically instead of sleeping for real (tests/smoke_sparring.php).
     */
    public function isExpired(array $session, ?int $now = null): bool
    {
        $ageSeconds = ($now ?? time()) - strtotime($session['lastActiveAt']);
        return $ageSeconds > SESSION_TTL_HOURS * 3600;
    }

    public function sessionStateFor(array $session): string
    {
        if ($session['tosAgreed'] === null) {
            return 'awaiting-decision';
        }
        if ($session['turnCount'] >= $this->effectiveAllowance($session)) {
            return 'complete';
        }
        return 'open';
    }

    /**
     * The session's turn ceiling: the base TURN_ALLOWANCE plus whatever extra
     * the visitor was granted at the limit via extendSession() (SA-01-8).
     * Tolerates a session array with no bonusTurns key so sessionStateFor()'s
     * pure array-literal checks in tests need no schema-shaped fixtures.
     */
    public function effectiveAllowance(array $session): int
    {
        return TURN_ALLOWANCE + ($session['bonusTurns'] ?? 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Turn processing (TF-01)
    |--------------------------------------------------------------------------
    */

    /**
     * TF-01: everything between a visitor pressing submit and a response
     * returning. Returns ['status' => ..., ...] rather than throwing, because
     * every one of these outcomes is an expected condition the caller must
     * branch on (TI-02), not a fault.
     *
     * Moderation gate (resolved gap — see plan doc §1, "reject-and-edit"):
     * runs immediately after the length check, before any provider call for
     * a response. On `unsuitable`, stops here: nothing is written to E-02,
     * the turn is not counted, and the visitor can edit and resubmit. This
     * satisfies SE-01 G-04 / SE-03 G-04 literally; it does not end the
     * session, per the user's resolution of the AP-05 vs G-04 conflict.
     *
     * $replyToExchangeId: QR-reply flow — only honored when this is the
     * session's first turn (turnCount === 0); silently ignored on any later
     * turn (stale chip left on screen, or a direct API call — both just fall
     * back to an ordinary turn, no error surfaced). When honored, the quoted
     * text is re-resolved here via Store::getQuotableExchange() — the id is
     * the only thing that crosses the trust boundary, a client-supplied quote
     * string is never trusted. An unknown/non-displayable id resolves to
     * null and is likewise treated as an ordinary turn.
     */
    public function processTurn(string $sessionId, string $rawContribution, ?int $replyToExchangeId = null): array
    {
        if ($this->llm === null || $this->rateLimiter === null) {
            throw new LogicException('processTurn needs an LlmClientInterface and RateLimiter');
        }

        // FS-01-1: request rate, cheapest and most likely to fire (checked before touching the store).
        $rateLimit = $this->rateLimiter->allow(RateLimiter::resolveClientOrigin());
        if (!$rateLimit['allowed']) {
            return ['status' => 'rate-limited', 'rateLimitRemaining' => $rateLimit['remaining']];
        }

        // FS-01-2: session must exist and be live.
        $session = $this->store->getSession($sessionId);
        if ($session === null || $this->isExpired($session)) {
            return ['status' => 'session-unknown'];
        }

        // FS-01-3: turn allowance (base plus any SA-01-8 extension).
        if ($session['turnCount'] >= $this->effectiveAllowance($session)) {
            return ['status' => 'turn-limit', 'rateLimitRemaining' => $rateLimit['remaining']];
        }

        // FS-01-4: trim + length bound, before any provider call.
        $contribution = trim($rawContribution);
        if ($contribution === '' || mb_strlen($contribution) > CONTRIBUTION_MAX_CHARS) {
            return ['status' => 'rejected', 'rateLimitRemaining' => $rateLimit['remaining']];
        }

        // Moderation gate — see method doc above for why this runs before generation.
        $suitability = $this->assessSuitability($contribution);
        if (!$suitability['suitable']) {
            return ['status' => 'content-flagged', 'rateLimitRemaining' => $rateLimit['remaining'], 'moderationReason' => $suitability['reason']];
        }

        // FS-01-7: prior exchanges as conversational context.
        $priorExchanges = $this->store->getExchanges($sessionId);

        // QR-reply flow (see method doc above): only ever meaningful on turn 1.
        // The quote is prepended to $storedContribution — what's persisted,
        // shown in the chat bubble/wall, and sent to the LLM — but never to
        // $contribution itself, which stays the visitor's own typed words for
        // the length check above (already run) and scenario derivation below
        // (a quote would otherwise hijack the wall's "scenario" heading).
        // Label prefix ("Replying to:" / dojo.replyQuote.label, same key as
        // the pre-send chip's heading): a cue for the model (not just the
        // human reader) that the quoted line is someone else's prior
        // response, not this visitor's own words. t() resolves off this
        // request's own locale (query/cookie/Accept-Language — see
        // resolve_locale()), i.e. the replying visitor's language, same as
        // dojo.js's withQuotePrefix builds for the optimistic bubble via
        // window.STRINGS.dojo.replyQuoteLabel — kept in sync deliberately.
        $quotedExchange = ($replyToExchangeId !== null && $session['turnCount'] === 0)
            ? $this->store->getQuotableExchange($replyToExchangeId)
            : null;
        $storedContribution = $quotedExchange !== null
            ? t('dojo.replyQuote.label') . " \"{$quotedExchange['text']}\"\n\n$contribution"
            : $contribution;

        // Turn-1 curriculum grounding (G-05, spec/L3-SE-04-system-prompt.adoc):
        // supplements the static <domain_grounding> block in prompts/sparring.md
        // with excerpts retrieved from this specific contribution — see
        // Store::searchCurriculumConcepts() for why vocabulary-restricted
        // matching, and AnthropicLlmClient::buildSystemBlocks()/
        // OpenAiLlmClient::buildSystemMessages() for why this travels
        // separately from $storedContribution rather than prepended to it
        // (never persisted/displayed; the static prompt's cache discount
        // must survive turn to turn). Runs on $contribution (the visitor's
        // own words), not $storedContribution, so a QR-reply quote never
        // feeds the lookup. Wrapped defensively: this is a supplementary
        // enhancement, not core to producing a response at all — a lookup
        // failure degrades to no grounding, never blocks the turn.
        //
        // Not simply "turnCount === 0": a QR-code-seeded session (dojo.js's
        // window.OPENING_MESSAGE, config.php's resolve_opening_message())
        // auto-sends a canned OPENING_MESSAGE_PREFIX-prefixed line as turn
        // 1, so the visitor's own first real words land on turn 2 instead —
        // that scripted opener has nothing to ground, and grounding it
        // there would mean the visitor's actual first turn never gets
        // grounded at all. So: look at what's actually in $priorExchanges
        // (already fetched above) rather than trust the turn count alone.
        $isVisitorsFirstRealContribution = match (count($priorExchanges)) {
            0 => !str_starts_with($contribution, OPENING_MESSAGE_PREFIX),
            1 => str_starts_with($priorExchanges[0]['visitorContribution'], OPENING_MESSAGE_PREFIX)
                && !str_starts_with($contribution, OPENING_MESSAGE_PREFIX),
            default => false,
        };
        $groundingContext = $isVisitorsFirstRealContribution ? $this->curriculumGroundingContext($contribution) : null;

        // FS-01-8: the provider call. Every failure path throws GenerationFailedException.
        $generationStart = microtime(true);
        try {
            $response = $this->llm->generateResponse($priorExchanges, $storedContribution, $groundingContext);
        } catch (GenerationFailedException) {
            return ['status' => 'generation-failed', 'rateLimitRemaining' => $rateLimit['remaining']];
        }
        $generationMs = (int) round((microtime(true) - $generationStart) * 1000);

        // FS-01-9/10: write the exchange, advance the turn count, in one operation (QR-07).
        $exchange = $this->store->appendExchange($sessionId, $storedContribution, $response, $quotedExchange['exchangeId'] ?? null);

        // FS-01-11: first exchange derives the scenario, written once (TF-05) —
        // from the clean $contribution, not the quote-prefixed $storedContribution.
        if ($exchange['position'] === 1) {
            $this->store->setScenario($sessionId, derive_scenario_statement($contribution), 'first-contribution');
        }

        $updated = $this->store->getSession($sessionId);

        return [
            'status' => 'ok',
            'exchange' => $exchange,
            'turnsRemaining' => $this->effectiveAllowance($updated) - $updated['turnCount'],
            'sessionState' => $this->sessionStateFor($updated),
            'rateLimitRemaining' => $rateLimit['remaining'],
            'generationMs' => $generationMs,
        ];
    }

    /**
     * SA-01-8: at the turn limit the visitor chose to keep going rather than
     * end the session. Lifts the ceiling by another TURN_ALLOWANCE and reports
     * the reopened state. No rate limiting and no generation here — one bounded
     * UPDATE, nothing a client can't already do by starting a fresh session.
     *
     * # ponytail: unmetered. A visitor could spam this, but what it lifts is a
     * ceiling on turns-that-cost-an-LLM-call, and those calls stay rate- and
     * size-limited by processTurn. Add a per-session extension cap here if it
     * is ever actually abused.
     */
    public function extendSession(string $sessionId): array
    {
        $session = $this->store->getSession($sessionId);
        if ($session === null || $this->isExpired($session)) {
            return ['status' => 'session-unknown'];
        }
        $this->store->extendSession($sessionId, TURN_ALLOWANCE);
        $updated = $this->store->getSession($sessionId);
        return [
            'status' => 'ok',
            'turnsRemaining' => $this->effectiveAllowance($updated) - $updated['turnCount'],
            'sessionState' => $this->sessionStateFor($updated),
        ];
    }

    /**
     * Turn-1 curriculum grounding — see processTurn's call site for the
     * design rationale. Returns null on no concept match (the common case:
     * most first turns won't literally name a page's own title) and on any
     * lookup failure (Store::searchCurriculumConcepts() is a supplementary
     * enhancement, never load-bearing for producing a response at all).
     */
    private function curriculumGroundingContext(string $contribution): ?string
    {
        try {
            $hits = $this->store->searchCurriculumConcepts($contribution);
        } catch (Throwable) {
            return null;
        }
        if ($hits === []) {
            return null;
        }

        $sections = array_map(
            static fn(array $hit): string => "## {$hit['title']}\n{$hit['excerpt']}",
            $hits
        );
        return "<curriculum_excerpts>\n"
            . "Retrieved from the Digital Design curriculum based on this contribution — may or may not be relevant; use only what actually fits.\n\n"
            . implode("\n\n", $sections)
            . "\n</curriculum_excerpts>";
    }

    /*
    |--------------------------------------------------------------------------
    | Title generation
    |--------------------------------------------------------------------------
    */

    /**
     * Generates and persists a session's header title, derived from its
     * first contribution. Called once, out of band from processTurn — by
     * the time this runs (public/api/title.php, fired fire-and-forget from
     * the client after turn 1's response already landed), the visitor-facing
     * turn is already complete, so nothing here is on that critical path.
     *
     * Two-branch, not null-on-failure: the LLM call is tried once; any
     * failure (timeout, malformed response, ...) falls back to the same
     * trim-based derivation TF-05 already uses for `scenario`
     * (derive_scenario_statement), just unbounded instead of capped at
     * SCENARIO_MAX_CHARS. Either path always produces a usable title.
     */
    public function generateAndStoreTitle(string $sessionId): string
    {
        if ($this->llm === null) {
            throw new LogicException('generateAndStoreTitle needs an LlmClientInterface');
        }

        $session = $this->store->getSession($sessionId);
        if ($session === null) {
            throw new RuntimeException("unknown session: $sessionId");
        }
        if ($session['title'] !== null) {
            return $session['title']; // already generated — never recomputed (mirrors scenario)
        }

        $exchanges = $this->store->getExchanges($sessionId);
        $firstContribution = $exchanges[0]['visitorContribution'] ?? null;
        if ($firstContribution === null) {
            throw new RuntimeException("session $sessionId has no exchange to derive a title from");
        }

        try {
            $title = $this->llm->generateTitle($firstContribution);
        } catch (Throwable) {
            $title = derive_scenario_statement($firstContribution, null);
        }

        $this->store->setTitle($sessionId, $title);
        return $title;
    }

    /*
    |--------------------------------------------------------------------------
    | Moderation (TF-02)
    |--------------------------------------------------------------------------
    */

    /**
     * TF-02: whether a contribution may appear on the public surface. Never
     * determines whether the visitor may continue (that's the caller's job,
     * and per the resolved moderation gap, "continue" now means "edit and
     * resubmit", not "proceed unaffected").
     *
     * Returns ['suitable' => bool, 'reason' => ?string] — reason is null when
     * suitable, otherwise one of 'blocked-term', 'contains-personal-information',
     * 'targets-real-person' (the classifier's own outcome), or
     * 'llm-classification' (the classifier call itself failed — real reason
     * unknown). The visitor-facing UI reads this to choose a coarse message;
     * the exact reason stays debug-only (?debug=1), see dojo.js.
     */
    public function assessSuitability(string $contribution): array
    {
        // FS-02-1: local term list, no provider call, cheapest rejection path.
        if ($this->containsBlockedTerm($contribution)) {
            return ['suitable' => false, 'reason' => 'blocked-term'];
        }

        // FS-02-2: single attempt, no retry, fails closed on ANY failure (QR-08).
        try {
            $classification = $this->llm->classify($contribution);
        } catch (Throwable) {
            return ['suitable' => false, 'reason' => 'llm-classification'];
        }

        $suitable = $classification === 'suitable';
        return ['suitable' => $suitable, 'reason' => $suitable ? null : $classification];
    }

    private function containsBlockedTerm(string $contribution): bool
    {
        static $terms = null;
        if ($terms === null) {
            $terms = [];
            $path = __DIR__ . '/../data/profanity_terms.txt';
            if (is_file($path)) {
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);
                    if ($line !== '' && !str_starts_with($line, '#')) {
                        $terms[] = preg_quote($line, '/');
                    }
                }
            }
        }
        if ($terms === []) {
            return false;
        }
        return (bool) preg_match('/\b(' . implode('|', $terms) . ')\b/iu', $contribution);
    }

    /*
    |--------------------------------------------------------------------------
    | Display assembly (TF-04)
    |--------------------------------------------------------------------------
    */

    /**
     * TF-04: assembles the ordered items the projection renders. Every decision
     * about what appears on the wall is made here, so SE-02 carries no policy (its C-02).
     */
    public function assembleDisplayMaterial(int $limit): array
    {
        $sessions = $this->store->getDisplayableSessions('live', $limit);
        if (count($sessions) < $limit) {
            $sessions = [...$sessions, ...$this->store->getDisplayableSessions('pilot', $limit - count($sessions))];
        }

        $items = [];
        $position = 0;
        foreach ($sessions as $session) {
            $exchange = $this->store->getLatestExchange($session['id']);
            if ($exchange === null) {
                continue; // guarded by the store query too, but never trust it twice for free
            }
            $items[] = [
                'sessionId' => $session['id'],
                'scenario' => $session['scenario'] ?? '',
                'title' => $session['title'] ?? null, // may not exist yet (TF-07 runs after turn 1) — client falls back to scenario
                'visitorContribution' => $exchange['visitorContribution'],
                'sparringResponse' => $exchange['sparringResponse'],
                'origin' => $session['origin'],
                'recencyRank' => $position++,
                'exchangeId' => $exchange['id'],
                'replyCount' => $exchange['replyCount'],
            ];
        }

        return $items;
    }
}
