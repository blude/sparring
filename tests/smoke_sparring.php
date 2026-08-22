<?php
declare(strict_types=1);

/**
 * Self-check for Sparring::processTurn — the 7-gate turn pipeline every
 * visitor exchange routes through (rate limit, session, expiry, turn
 * allowance, length, moderation, generation) — plus the pure
 * sessionStateFor()/isExpired() helpers. Uses a fake LlmClientInterface,
 * no network, no real API key. Real Store on a tmp SQLite path.
 * Run: php tests/smoke_sparring.php
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';
require __DIR__ . '/../src/LlmClientInterface.php';
require __DIR__ . '/../src/RateLimiter.php';
require __DIR__ . '/../src/Sparring.php';

/** Canned LlmClientInterface — every branch is caller-configurable, no network. */
final class FakeLlmClient implements LlmClientInterface
{
    public string $classifyResult = 'suitable';
    public bool $throwOnClassify = false;
    public bool $throwOnGenerate = false;
    public string $cannedResponse = 'What makes you think it has a clean answer?';
    public ?string $lastNewContribution = null; // QR-reply flow: what processTurn actually sent to the LLM

    public function generateResponse(array $priorExchanges, string $newContribution): string
    {
        $this->lastNewContribution = $newContribution;
        if ($this->throwOnGenerate) {
            throw new GenerationFailedException('fake generation failure');
        }
        return $this->cannedResponse;
    }

    public function classify(string $contribution): string
    {
        if ($this->throwOnClassify) {
            throw new RuntimeException('fake classify failure');
        }
        return $this->classifyResult;
    }

    public function generateTitle(string $contribution): string
    {
        return 'canned title';
    }
}

$dbPath = sys_get_temp_dir() . '/sparring_smoke_sparring_' . bin2hex(random_bytes(4)) . '.db';
$store = new Store($dbPath);
$llm = new FakeLlmClient();
$rateLimiter = new RateLimiter($store);
$sparring = new Sparring($store, $llm, $rateLimiter);

// RateLimiter::resolveClientOrigin() reads $_SERVER directly (TF-03) — give
// each sub-test its own REMOTE_ADDR so their rate-limit windows don't collide.
function withOrigin(string $addr, callable $fn): void
{
    $_SERVER['REMOTE_ADDR'] = $addr;
    $fn();
}

/*
|--------------------------------------------------------------------------
| pure helpers, no DB/network at all
|--------------------------------------------------------------------------
*/

// sessionStateFor: 3-branch decision, pure given a plain array.
assert($sparring->sessionStateFor(['tosAgreed' => null, 'turnCount' => 0]) === 'awaiting-decision');
assert($sparring->sessionStateFor(['tosAgreed' => true, 'turnCount' => 0]) === 'open');
assert($sparring->sessionStateFor(['tosAgreed' => true, 'turnCount' => TURN_ALLOWANCE]) === 'complete');
assert($sparring->sessionStateFor(['tosAgreed' => false, 'turnCount' => 0]) === 'open'); // false !== null: decided, just declined

// isExpired: only reads time() + the passed lastActiveAt, no DB.
$fresh = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
$stale = gmdate('Y-m-d\TH:i:s\Z', time() - (SESSION_TTL_HOURS * 3600 + 60));
assert($sparring->isExpired(['lastActiveAt' => $fresh]) === false);
assert($sparring->isExpired(['lastActiveAt' => $stale]) === true);

// Same check via the injectable $now — decoupled from the real wall clock entirely.
$fixedNow = 2_000_000_000; // arbitrary fixed epoch instant
$fixedFresh = gmdate('Y-m-d\TH:i:s\Z', $fixedNow - 60);
$fixedStale = gmdate('Y-m-d\TH:i:s\Z', $fixedNow - (SESSION_TTL_HOURS * 3600 + 60));
assert($sparring->isExpired(['lastActiveAt' => $fixedFresh], $fixedNow) === false);
assert($sparring->isExpired(['lastActiveAt' => $fixedStale], $fixedNow) === true);

// RateLimiter::resolveClientOrigin: XFF multi-hop takes the LAST entry (see
// its own docblock — nginx here appends the real IP rather than overwriting).
unset($_SERVER['HTTP_X_FORWARDED_FOR']);
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
assert(RateLimiter::resolveClientOrigin() === '203.0.113.9'); // no XFF: falls back to REMOTE_ADDR
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
assert(RateLimiter::resolveClientOrigin() === '198.51.100.1'); // single hop
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 2.214.252.236';
assert(RateLimiter::resolveClientOrigin() === '2.214.252.236'); // multi-hop: last, not first
$_SERVER['HTTP_X_FORWARDED_FOR'] = ' 1.2.3.4 ,  2.214.252.236  ';
assert(RateLimiter::resolveClientOrigin() === '2.214.252.236'); // surrounding whitespace trimmed
$_SERVER['HTTP_X_FORWARDED_FOR'] = '';
assert(RateLimiter::resolveClientOrigin() === '203.0.113.9'); // empty XFF: falls back to REMOTE_ADDR too
unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
assert(RateLimiter::resolveClientOrigin() === 'unknown'); // neither set

/*
|--------------------------------------------------------------------------
| processTurn gates, each on its own rate-limit bucket
|--------------------------------------------------------------------------
*/

// FS-01-9/10/11: happy path, exchange persisted, scenario set once (first turn only).
withOrigin('10.0.0.1', function () use ($store, $sparring) {
    $session = $store->createSession('live');
    $result = $sparring->processTurn($session['id'], '  What is a wicked problem?  ');
    assert($result['status'] === 'ok');
    assert($result['exchange']['position'] === 1);
    assert($result['exchange']['visitorContribution'] === 'What is a wicked problem?'); // trimmed
    assert($result['turnsRemaining'] === TURN_ALLOWANCE - 1);
    assert($result['sessionState'] === 'awaiting-decision'); // consent not recorded yet — processTurn doesn't gate on it
    assert($result['rateLimitRemaining'] === RATE_LIMIT_MAX_REQUESTS - 1);

    $afterFirst = $store->getSession($session['id']);
    assert($afterFirst['scenario'] !== null);
    $scenarioAfterFirst = $afterFirst['scenario'];

    $result2 = $sparring->processTurn($session['id'], 'A second contribution.');
    assert($result2['status'] === 'ok');
    assert($result2['exchange']['position'] === 2);
    $afterSecond = $store->getSession($session['id']);
    assert($afterSecond['scenario'] === $scenarioAfterFirst); // TF-05: written once, never recomputed
});

// FS-01-4: empty and over-length contributions are rejected before any provider call.
withOrigin('10.0.0.2', function () use ($store, $sparring) {
    $session = $store->createSession('live');
    assert($sparring->processTurn($session['id'], '   ')['status'] === 'rejected');
    $tooLong = str_repeat('a', CONTRIBUTION_MAX_CHARS + 1);
    assert($sparring->processTurn($session['id'], $tooLong)['status'] === 'rejected');
    assert($store->getSession($session['id'])['turnCount'] === 0); // neither attempt counted as a turn
});

// FS-02-1: local blocked-term list short-circuits before any classify() call.
withOrigin('10.0.0.3', function () use ($store, $sparring) {
    $termsPath = __DIR__ . '/../data/profanity_terms.txt';
    $term = null;
    foreach (file($termsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line !== '' && !str_starts_with($line, '#')) {
            $term = $line;
            break;
        }
    }
    assert($term !== null); // fixture file must have at least one real term

    $session = $store->createSession('live');
    $result = $sparring->processTurn($session['id'], "a sentence containing $term as a word");
    assert($result['status'] === 'content-flagged');
    assert($result['moderationReason'] === 'blocked-term');
    assert($store->getSession($session['id'])['turnCount'] === 0);
});

// FS-02-2: classifier says unsuitable — reason passed straight through.
withOrigin('10.0.0.4', function () use ($store, $sparring, $llm) {
    $session = $store->createSession('live');
    $llm->classifyResult = 'targets-real-person';
    $result = $sparring->processTurn($session['id'], 'a contribution about someone specific');
    $llm->classifyResult = 'suitable'; // reset for later sub-tests
    assert($result['status'] === 'content-flagged');
    assert($result['moderationReason'] === 'targets-real-person');
});

// QR-08: classify() itself failing fails closed, reason is the generic 'llm-classification'.
withOrigin('10.0.0.5', function () use ($store, $sparring, $llm) {
    $session = $store->createSession('live');
    $llm->throwOnClassify = true;
    $result = $sparring->processTurn($session['id'], 'a perfectly ordinary contribution');
    $llm->throwOnClassify = false;
    assert($result['status'] === 'content-flagged');
    assert($result['moderationReason'] === 'llm-classification');
});

// FS-01-8: generation failure maps to 'generation-failed', nothing persisted.
withOrigin('10.0.0.6', function () use ($store, $sparring, $llm) {
    $session = $store->createSession('live');
    $llm->throwOnGenerate = true;
    $result = $sparring->processTurn($session['id'], 'a perfectly ordinary contribution');
    $llm->throwOnGenerate = false;
    assert($result['status'] === 'generation-failed');
    assert($store->getSession($session['id'])['turnCount'] === 0);
});

// FS-01-2: unknown session id.
withOrigin('10.0.0.7', function () use ($sparring) {
    assert($sparring->processTurn('DOESNOTEXIST', 'hi')['status'] === 'session-unknown');
});

// FS-01-3: turn allowance already exhausted (rate limiter fresh — isolates this gate from rate-limiting).
withOrigin('10.0.0.8', function () use ($store, $sparring) {
    $session = $store->createSession('live');
    for ($i = 0; $i < TURN_ALLOWANCE; $i++) {
        $store->appendExchange($session['id'], "turn $i", 'a response'); // bypass processTurn/rate limiter directly
    }
    $result = $sparring->processTurn($session['id'], 'one more, please');
    assert($result['status'] === 'turn-limit');
    assert($store->getSession($session['id'])['turnCount'] === TURN_ALLOWANCE); // not incremented further
});

// FS-01-1: rate limit exhausted, well within TURN_ALLOWANCE. processTurn checks
// the rate gate before turn count unconditionally, so this fires regardless of
// how the two constants compare.
withOrigin('10.0.0.9', function () use ($store, $sparring) {
    $session = $store->createSession('live');
    for ($i = 0; $i < RATE_LIMIT_MAX_REQUESTS; $i++) {
        $result = $sparring->processTurn($session['id'], "turn $i");
        assert($result['status'] === 'ok');
        assert($result['rateLimitRemaining'] === RATE_LIMIT_MAX_REQUESTS - 1 - $i);
    }
    $result = $sparring->processTurn($session['id'], 'over the limit');
    assert($result['status'] === 'rate-limited');
    assert($result['rateLimitRemaining'] === 0);
});

/*
|--------------------------------------------------------------------------
| QR-reply flow: processTurn's optional $replyToExchangeId
|--------------------------------------------------------------------------
*/

// Honored on turn 1: quoted text is re-resolved server-side and prepended to
// both what's persisted/returned (chat bubble, wall) and what the LLM sees —
// the same string, so the two can never drift out of sync with each other.
withOrigin('10.0.1.1', function () use ($store, $sparring, $llm) {
    $quotedSession = $store->createSession('live');
    $quoted = $store->appendExchange($quotedSession['id'], 'a first visitor', 'the quoted sparring response');
    $store->setDisplayable($quotedSession['id'], true); // getQuotableExchange requires displayable

    $replier = $store->createSession('live');
    $result = $sparring->processTurn($replier['id'], 'I disagree.', $quoted['id']);
    assert($result['status'] === 'ok');
    $expected = "Replying to: \"the quoted sparring response\"\n\nI disagree.";
    assert($result['exchange']['visitorContribution'] === $expected); // quote-prefixed, persisted as-is
    assert($llm->lastNewContribution === $expected); // exact same string reached the LLM
    assert($store->getQuotableExchange($quoted['id'])['text'] === 'the quoted sparring response'); // unchanged
    assert($store->getExchanges($quotedSession['id'])[0]['replyCount'] === 1); // incremented exactly once

    // Ignored on turn 2 of the same session — not the first turn anymore.
    $result2 = $sparring->processTurn($replier['id'], 'a second turn', $quoted['id']);
    assert($result2['status'] === 'ok');
    assert($result2['exchange']['visitorContribution'] === 'a second turn'); // clean, no quote prefix
    assert($llm->lastNewContribution === 'a second turn');
    assert($store->getExchanges($quotedSession['id'])[0]['replyCount'] === 1); // unchanged
});

// Unknown/non-displayable id on turn 1 falls through silently as an ordinary turn.
withOrigin('10.0.1.2', function () use ($store, $sparring, $llm) {
    $session = $store->createSession('live');
    $result = $sparring->processTurn($session['id'], 'a normal reply', 999999);
    assert($result['status'] === 'ok');
    assert($result['exchange']['visitorContribution'] === 'a normal reply');
    assert($llm->lastNewContribution === 'a normal reply'); // no quote prefix — unresolved id, not honored
});

unlink($dbPath);
foreach (['-wal', '-shm'] as $suffix) {
    @unlink($dbPath . $suffix);
}

echo "smoke_sparring: ok\n";
