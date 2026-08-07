<?php
declare(strict_types=1);

/** TI-03 — Retrieve an existing session and its exchanges, for UC-03's reload-recovery path. */

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../src/Store.php';
require __DIR__ . '/../../src/Sparring.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method-not-allowed']);
    exit;
}

$sessionId = $_GET['sessionId'] ?? null;
if (!is_string($sessionId) || $sessionId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'malformed-request']);
    exit;
}

$store = new Store(STORE_DB_PATH);
$sparring = new Sparring($store);

$session = $store->getSession($sessionId);
if ($session === null || $sparring->isExpired($session)) {
    http_response_code(404);
    echo json_encode(['error' => 'session-unknown']);
    exit;
}

echo json_encode([
    'sessionId' => $session['id'],
    'sessionState' => $sparring->sessionStateFor($session),
    'turnsRemaining' => TURN_ALLOWANCE - $session['turnCount'],
    'retentionDecided' => $session['consentGranted'] !== null,
    'origin' => $session['origin'], // debug mode (?debug=1) only consumer
    'exchanges' => array_map(
        static fn (array $e) => [
            'visitorContribution' => $e['visitorContribution'],
            'sparringResponse' => $e['sparringResponse'],
            'position' => $e['position'],
        ],
        $store->getExchanges($session['id'])
    ),
], JSON_UNESCAPED_SLASHES);
