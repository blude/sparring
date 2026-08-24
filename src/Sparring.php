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
        if ($session['turnCount'] >= TURN_ALLOWANCE) {
            return 'complete';
        }
        return 'open';
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

        // FS-01-3: turn allowance.
        if ($session['turnCount'] >= TURN_ALLOWANCE) {
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

        // FS-01-8: the provider call. Every failure path throws GenerationFailedException.
        $generationStart = microtime(true);
        try {
            $response = $this->llm->generateResponse($priorExchanges, $storedContribution);
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
            'turnsRemaining' => TURN_ALLOWANCE - $updated['turnCount'],
            'sessionState' => $this->sessionStateFor($updated),
            'rateLimitRemaining' => $rateLimit['remaining'],
            'generationMs' => $generationMs,
        ];
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
     * (derive_scenario_statement), just capped at TITLE_MAX_CHARS instead of
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
            $title = derive_scenario_statement($firstContribution, TITLE_MAX_CHARS);
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
    public function assembleDisplayMaterial(int $limit = DISPLAY_ITEM_LIMIT): array
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
