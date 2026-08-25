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

/*
|--------------------------------------------------------------------------
| session create + origin round-trip
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| consent decision, unknown session
|--------------------------------------------------------------------------
*/

// Agrees to ToS and projection, declines retention — independent opt-ins (SC-05).
$store->recordConsentDecision($session['id'], true, false, true);
$afterConsent = $store->getSession($session['id']);
assert($afterConsent['tosAgreed'] === true);
assert($afterConsent['consentGranted'] === false);
assert($afterConsent['projectionConsent'] === true);
assert($afterConsent['displayable'] === true); // driven by projection choice, not a separate flag
assert($store->getSession('does-not-exist') === null);

/*
|--------------------------------------------------------------------------
| setConsent: the retention-only decision call, independent of recordConsentDecision above
|--------------------------------------------------------------------------
*/

$consentOnlySession = $store->createSession('live');
assert($store->getSession($consentOnlySession['id'])['consentGranted'] === null);
$store->setConsent($consentOnlySession['id'], true);
assert($store->getSession($consentOnlySession['id'])['consentGranted'] === true);
$store->setConsent($consentOnlySession['id'], false);
assert($store->getSession($consentOnlySession['id'])['consentGranted'] === false); // not one-way, unlike setDisplayable

/*
|--------------------------------------------------------------------------
| setTitle: write-once, guarded by `title IS NULL`
|--------------------------------------------------------------------------
*/

$titleSession = $store->createSession('live');
assert($store->getSession($titleSession['id'])['title'] === null);
$store->setTitle($titleSession['id'], 'What is a wicked problem?');
assert($store->getSession($titleSession['id'])['title'] === 'What is a wicked problem?');
$store->setTitle($titleSession['id'], 'a later call must not overwrite');
assert($store->getSession($titleSession['id'])['title'] === 'What is a wicked problem?');

/*
|--------------------------------------------------------------------------
| saveEvaluation: always-skippable end-of-session feedback
|--------------------------------------------------------------------------
*/

$evalSession = $store->createSession('live');
assert($store->getEvaluation($evalSession['id']) === null);
assert($store->saveEvaluation($evalSession['id'], ['challenge' => 4], 'Sparring pushed back hard.') === true);
$evaluation = $store->getEvaluation($evalSession['id']);
assert($evaluation['answers'] === ['challenge' => 4]);
assert($evaluation['feedback'] === 'Sparring pushed back hard.');
assert($store->saveEvaluation($evalSession['id'], [], null) === false); // nothing to write is a no-op, not an empty row
assert($store->getEvaluation($evalSession['id'])['feedback'] === 'Sparring pushed back hard.'); // unchanged by the no-op above
assert($store->saveEvaluation('does-not-exist', ['challenge' => 3], null) === false); // unknown session, no write
assert($store->saveEvaluation($evalSession['id'], ['challenge' => 5], 'a resubmit overwrites') === true);
assert($store->getEvaluation($evalSession['id'])['answers'] === ['challenge' => 5]); // INSERT OR REPLACE, not an error

/*
|--------------------------------------------------------------------------
| exchange + turn count
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| QR-reply flow: reply_count + getQuotableExchange
|--------------------------------------------------------------------------
*/

assert($exchange['replyCount'] === 0); // a freshly-appended exchange never has replies of its own yet

// getQuotableExchange: unknown id; a non-displayable session's exchange
// ($consentOnlySession never granted projection consent, so displayable stays
// false); and $session, which recordConsentDecision above already made
// displayable (projectionGranted: true), resolves to the narrow shape.
$nonDisplayableExchange = $store->appendExchange($consentOnlySession['id'], 'not shown', 'not shown either');
assert($store->getQuotableExchange(999999) === null);
assert($store->getQuotableExchange($nonDisplayableExchange['id']) === null);
assert($store->getQuotableExchange($exchange['id']) === ['exchangeId' => $exchange['id'], 'text' => $exchange['sparringResponse']]);

// appendExchange's reply-count increment: only fires when position === 1 AND
// a replyToExchangeId is passed — exactly-once, same transaction as the insert.
$replierSession = $store->createSession('live');
$repliedExchange = $store->appendExchange($replierSession['id'], 'I disagree.', 'On what grounds?', $exchange['id']);
assert($repliedExchange['position'] === 1);
assert($store->getQuotableExchange($exchange['id'])['exchangeId'] === $exchange['id']); // still resolves
assert($store->getExchanges($session['id'])[0]['replyCount'] === 1); // the quoted exchange, incremented

// a later turn (position !== 1) passing a replyToExchangeId must NOT increment
$store->appendExchange($replierSession['id'], 'a second turn', 'a second response', $exchange['id']);
assert($store->getExchanges($session['id'])[0]['replyCount'] === 1); // unchanged

/*
|--------------------------------------------------------------------------
| displayable / scenario
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| getAllSessions / getAllExchanges (bin/export.php: every row, any origin)
|--------------------------------------------------------------------------
*/

$allSessionIds = array_column($store->getAllSessions(), 'id');
foreach ([$session['id'], $consentOnlySession['id'], $titleSession['id'], $evalSession['id'], $replierSession['id'], $pilotSession['id']] as $id) {
    assert(in_array($id, $allSessionIds, true));
}
assert(count($allSessionIds) === 6); // every session created so far, live and pilot alike
$allExchanges = $store->getAllExchanges();
assert(count($allExchanges) === 6); // 2 on $session, 1 on $consentOnlySession, 2 on $replierSession, 1 on $pilotSession

/*
|--------------------------------------------------------------------------
| rate limiting
|--------------------------------------------------------------------------
*/

$hash = hash('sha256', '203.0.113.7');
for ($i = 0; $i < 3; $i++) {
    assert($store->checkAndIncrementRateLimit($hash, 60, 3)['allowed'] === true);
}
assert($store->checkAndIncrementRateLimit($hash, 60, 3)['allowed'] === false); // 4th request in window exceeds limit of 3

/*
|--------------------------------------------------------------------------
| rate limiting: window-boundary math via the injectable $now, no sleep()
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| maintenance: counts, reset, backup
|--------------------------------------------------------------------------
*/

$before = $store->getCounts();
assert($before['sessions'] === 6); // $session, $consentOnlySession, $titleSession, $evalSession, $replierSession, $pilotSession created above
assert($before['exchanges'] === 6); // 2 on $session, 1 on $consentOnlySession, 2 on $replierSession, 1 on $pilotSession
assert($before['rateLimitWindows'] === 2); // $hash + $boundaryHash above
$deleted = $store->resetAll();
assert($deleted === $before);
assert($store->getCounts() === ['sessions' => 0, 'exchanges' => 0, 'rateLimitWindows' => 0]);

$backupPath = sys_get_temp_dir() . '/sparring-smoke-backup-' . getmypid() . '.db';
$store->backupTo($backupPath);
assert(is_file($backupPath));
unlink($backupPath);

/*
|--------------------------------------------------------------------------
| maintenance: prune orphaned sessions
|--------------------------------------------------------------------------
*/

$orphan = $store->createSession('live'); // zero exchanges — orphan candidate
$busy = $store->createSession('live');
$store->appendExchange($busy['id'], 'a contribution', 'a response');

// A zero-turn session can still carry an evaluation — "End session" is
// always available and shows the feedback dialog regardless of turn count
// (UI-01). Pruning it must cascade the evaluation row, not FK-violate.
$store->saveEvaluation($orphan['id'], [], 'left before typing anything');

assert($store->countOrphanedSessions(999999) === 0); // too recent to count as stale — never touches an in-progress visitor
assert($store->countOrphanedSessions(-3600) === 1); // cutoff pushed into the future — only the zero-exchange session qualifies

assert($store->pruneOrphanedSessions(-3600) === 1);
assert($store->getSession($orphan['id']) === null);
assert($store->getEvaluation($orphan['id']) === null); // cascaded, not orphaned in its own table
assert($store->getSession($busy['id']) !== null); // has an exchange, never an orphan regardless of age

// --- curriculum search (poor man's RAG ingestion, bin/import_curriculum.php) ---
assert($store->getCurriculumChunkCount() === 0);

$replaceCounts = $store->replaceCurriculumChunks([
    ['title' => 'Wicked Problems', 'body' => 'A wicked problem resists a single clean fix.', 'path' => 'wicked-problems.md'],
    ['title' => 'Feasibility Desirability Viability', 'body' => 'A wicked design decision balances feasibility, desirability and viability.', 'path' => 'feasibility.md'],
]);
assert($replaceCounts === ['before' => 0, 'after' => 2]);
assert($store->getCurriculumChunkCount() === 2);

$uniqueHit = $store->searchCurriculum('resists');
assert(count($uniqueHit) === 1 && $uniqueHit[0]['path'] === 'wicked-problems.md');

$sharedHit = $store->searchCurriculum('wicked'); // present in both bodies
assert(count($sharedHit) === 2);
foreach ($sharedHit as $row) {
    assert(is_float($row['score']));
}

// simulates wicked-problems.md having been deleted from disk before the next import run
$replaceCounts = $store->replaceCurriculumChunks([
    ['title' => 'Feasibility Desirability Viability', 'body' => 'A wicked design decision balances feasibility, desirability and viability.', 'path' => 'feasibility.md'],
]);
assert($replaceCounts === ['before' => 2, 'after' => 1]);
assert($store->getCurriculumChunkCount() === 1);
assert($store->searchCurriculum('resists') === []); // dropped chunk's content no longer matches anything

// pathological queries (FTS5 query-syntax edge cases) must not throw
assert($store->searchCurriculum('"unterminated') === []); // no matching token in the remaining corpus
$dashResult = $store->searchCurriculum('-viability'); // leading '-' stripped by the sanitizer, not read as FTS5 NOT
assert(count($dashResult) === 1 && $dashResult[0]['path'] === 'feasibility.md');
assert($store->searchCurriculum('col:value') === []); // neither token present
assert($store->searchCurriculum('.') === []); // no word/number tokens at all
assert($store->searchCurriculum('') === []);

// a token that collides with an FTS5 keyword (and/or/not/near) must still be
// treated as a literal search term, not parsed as an operator — the exact
// remaining chunk's body contains the literal word "and"
$andResult = $store->searchCurriculum('and');
assert(count($andResult) === 1 && $andResult[0]['path'] === 'feasibility.md');

unlink($dbPath);
foreach (['-wal', '-shm'] as $suffix) {
    @unlink($dbPath . $suffix);
}

echo "smoke_store: ok\n";
