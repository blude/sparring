<?php
declare(strict_types=1);

/**
 * M0 self-check: create DB, write a session + exchange, read back, verify
 * turn count and origin round-trip, verify rate limiting and display query.
 * Run: php tests/smoke_store.php
 */

require __DIR__ . '/../src/Store.php';

$dbPath = sys_get_temp_dir() . '/sparring_smoke_' . bin2hex(random_bytes(4)) . '.db';
$store = new Store($dbPath);

// --- session create + origin round-trip ---
$session = $store->createSession('live');
assert($session['origin'] === 'live');
assert($session['turnCount'] === 0);
assert($session['displayable'] === false); // nothing projects until the visitor opts in
assert($session['consentGranted'] === null);
assert($session['tosAgreed'] === null);
assert($session['projectionConsent'] === null);

$fetched = $store->getSession($session['id']);
assert($fetched !== null);
assert($fetched['id'] === $session['id']);

// --- consent decision, unknown session ---
// Agrees to ToS and projection, declines retention — independent opt-ins (SC-05).
$store->recordConsentDecision($session['id'], true, false, true);
$afterConsent = $store->getSession($session['id']);
assert($afterConsent['tosAgreed'] === true);
assert($afterConsent['consentGranted'] === false);
assert($afterConsent['projectionConsent'] === true);
assert($afterConsent['displayable'] === true); // driven by projection choice, not a separate flag
assert($store->getSession('does-not-exist') === null);

// --- exchange + turn count ---
$exchange = $store->appendExchange($session['id'], 'What is a wicked problem?', 'What makes you think it has a clean answer?');
assert($exchange['position'] === 1);

$afterFirst = $store->getSession($session['id']);
assert($afterFirst['turnCount'] === 1);

$store->appendExchange($session['id'], 'Fine, it resists a single fix.', 'What resists it, specifically?');
$afterSecond = $store->getSession($session['id']);
assert($afterSecond['turnCount'] === 2);

$exchanges = $store->getExchanges($session['id']);
assert(count($exchanges) === 2);
assert($exchanges[0]['position'] === 1 && $exchanges[1]['position'] === 2);

$latest = $store->getLatestExchange($session['id']);
assert($latest['position'] === 2);

// --- displayable / scenario ---
$store->setScenario($session['id'], 'What is a wicked problem?', 'first-contribution');
$store->setDisplayable($session['id'], true);
$visible = $store->getDisplayableSessions('live', 10);
assert(count($visible) === 1 && $visible[0]['id'] === $session['id']);

$store->setDisplayable($session['id'], false);
$hiddenNow = $store->getDisplayableSessions('live', 10);
assert(count($hiddenNow) === 0);

// pilot session must not leak into a 'live' query
$pilotSession = $store->createSession('pilot');
$store->appendExchange($pilotSession['id'], 'pilot contribution', 'pilot response');
$store->setDisplayable($pilotSession['id'], true);
assert(count($store->getDisplayableSessions('live', 10)) === 0);
assert(count($store->getDisplayableSessions('pilot', 10)) === 1);

// --- rate limiting ---
$hash = hash('sha256', '203.0.113.7');
for ($i = 0; $i < 3; $i++) {
    assert($store->checkAndIncrementRateLimit($hash, 60, 3)['allowed'] === true);
}
assert($store->checkAndIncrementRateLimit($hash, 60, 3)['allowed'] === false); // 4th request in window exceeds limit of 3

// --- rate limiting: window-boundary math via the injectable $now, no sleep() ---
// window_start itself still stamps the real wall clock (self::now(), unchanged
// by this param) — offsets below are margined generously so a slow test run
// can't land on the wrong side of a boundary.
$boundaryHash = hash('sha256', 'rate-limit-boundary-origin');
$t0 = time();
$b1 = $store->checkAndIncrementRateLimit($boundaryHash, 10, 5, $t0);
assert($b1 === ['allowed' => true, 'remaining' => 4]);
$b2 = $store->checkAndIncrementRateLimit($boundaryHash, 10, 5, $t0 + 5); // still inside the 10s window
assert($b2 === ['allowed' => true, 'remaining' => 3]);
$b3 = $store->checkAndIncrementRateLimit($boundaryHash, 10, 5, $t0 + 15); // past the window: resets, not a continuation
assert($b3 === ['allowed' => true, 'remaining' => 4]);

// --- maintenance: counts, reset, backup ---
$before = $store->getCounts();
assert($before['sessions'] === 2); // $session + $pilotSession created above
assert($before['exchanges'] === 3); // 2 on $session, 1 on $pilotSession
assert($before['rateLimitWindows'] === 2); // $hash + $boundaryHash above
$deleted = $store->resetAll();
assert($deleted === $before);
assert($store->getCounts() === ['sessions' => 0, 'exchanges' => 0, 'rateLimitWindows' => 0]);

$backupPath = sys_get_temp_dir() . '/sparring-smoke-backup-' . getmypid() . '.db';
$store->backupTo($backupPath);
assert(is_file($backupPath));
unlink($backupPath);

// --- maintenance: prune orphaned sessions ---
$orphan = $store->createSession('live'); // zero exchanges — orphan candidate
$busy = $store->createSession('live');
$store->appendExchange($busy['id'], 'a contribution', 'a response');

assert($store->countOrphanedSessions(999999) === 0); // too recent to count as stale — never touches an in-progress visitor
assert($store->countOrphanedSessions(-3600) === 1); // cutoff pushed into the future — only the zero-exchange session qualifies

assert($store->pruneOrphanedSessions(-3600) === 1);
assert($store->getSession($orphan['id']) === null);
assert($store->getSession($busy['id']) !== null); // has an exchange, never an orphan regardless of age

unlink($dbPath);
foreach (['-wal', '-shm'] as $suffix) {
    @unlink($dbPath . $suffix);
}

echo "smoke_store: ok\n";
