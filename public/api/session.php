<?php
declare(strict_types=1);

/**
 * TI-01 — Open a session. Two calls share this endpoint, distinguished by body
 * shape: no `sessionId` creates a session; `sessionId` + the three consent
 * booleans records the decision against an existing one.
 *
 * ToS agreement is required to participate at all (rejected here, before
 * Sparring/Store see it, so it never becomes a stored "declined" state).
 * Retention and projection are independent opt-ins — SC-05 explicitly rules
 * out one switch governing both, so a visitor can decline either (or both)
 * and still spar; declining projection just means displayable stays false.
 */

require __DIR__ . '/../../src/Store.php';
require __DIR__ . '/../../src/Sparring.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method-not-allowed']);
    exit;
}

$store = new Store(STORE_DB_PATH);
$sparring = new Sparring($store);

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    $body = [];
}

function respond_session(Sparring $sparring, array $session): void
{
    echo json_encode([
        'sessionId' => $session['id'],
        'sessionState' => $sparring->sessionStateFor($session),
        'turnsRemaining' => TURN_ALLOWANCE - $session['turnCount'],
        'origin' => $session['origin'], // debug mode (?debug=1) only consumer
    ], JSON_UNESCAPED_SLASHES);
}

if (!isset($body['sessionId'])) {
    // creation call — origin fixed as 'live' here (SQR-05); pilot origin is only ever TF-06/TI-05.
    $session = $store->createSession('live');
    respond_session($sparring, $session);
    exit;
}

$sessionId = $body['sessionId'];
$fieldsPresent = array_key_exists('tosAgreed', $body)
    && array_key_exists('retentionGranted', $body)
    && array_key_exists('projectionGranted', $body);
$fieldsAreBool = $fieldsPresent
    && is_bool($body['tosAgreed']) && is_bool($body['retentionGranted']) && is_bool($body['projectionGranted']);

if (!is_string($sessionId) || !$fieldsAreBool) {
    http_response_code(400);
    echo json_encode(['error' => 'malformed-request']);
    exit;
}

$session = $store->getSession($sessionId);
if ($session === null || $sparring->isExpired($session)) {
    http_response_code(404);
    echo json_encode(['error' => 'session-unknown']);
    exit;
}

if ($body['tosAgreed'] !== true) {
    // Required to participate at all — rejected before it becomes a stored
    // "declined" state, unlike retention/projection which are real opt-outs.
    http_response_code(400);
    echo json_encode(['error' => 'tos-required']);
    exit;
}

$store->recordConsentDecision($sessionId, true, $body['retentionGranted'], $body['projectionGranted']);
respond_session($sparring, $store->getSession($sessionId));
