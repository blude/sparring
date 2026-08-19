<?php
declare(strict_types=1);

/** TI-02 — Submit a contribution, run TF-01, return the completed exchange or a condition. */

require __DIR__ . '/../../src/Store.php';
require __DIR__ . '/../../src/RateLimiter.php';
require __DIR__ . '/../../src/LlmClientInterface.php';
require __DIR__ . '/../../src/AnthropicLlmClient.php';
require __DIR__ . '/../../src/OpenAiLlmClient.php';
require __DIR__ . '/../../src/Sparring.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method-not-allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body) || !isset($body['sessionId'], $body['contribution']) || !is_string($body['sessionId']) || $body['sessionId'] === '' || !is_string($body['contribution'])) {
    http_response_code(400);
    echo json_encode(['error' => 'malformed-request']);
    exit;
}

$store = new Store(STORE_DB_PATH);
$llm = createLlmClient();
$rateLimiter = new RateLimiter($store);
$sparring = new Sparring($store, $llm, $rateLimiter);

$result = $sparring->processTurn($body['sessionId'], $body['contribution']);

// Every non-'ok' status is an expected condition (TI-02's own error-case list),
// mapped to the HTTP code closest in meaning — none of these are 5xx faults.
$statusCodes = [
    'ok' => 200,
    'rate-limited' => 429,
    'session-unknown' => 404,
    'turn-limit' => 409,
    'rejected' => 400,
    'content-flagged' => 422, // reject-and-edit: visitor edits the same text and resubmits
    'generation-failed' => 502,
];
http_response_code($statusCodes[$result['status']] ?? 500);

// Debug fields (?debug=1 in the client) are cheap to always compute, so no
// server-side notion of "debug" is needed — just pass through what's present.
$debugFields = array_filter(
    [
        'rateLimitRemaining' => $result['rateLimitRemaining'] ?? null,
        'generationMs' => $result['generationMs'] ?? null,
        'moderationReason' => $result['moderationReason'] ?? null,
    ],
    static fn ($v) => $v !== null
);

if ($result['status'] === 'ok') {
    echo json_encode([
        'status' => 'ok',
        'exchange' => [
            'visitorContribution' => $result['exchange']['visitorContribution'],
            'sparringResponse' => $result['exchange']['sparringResponse'],
        ],
        'turnsRemaining' => $result['turnsRemaining'],
        'sessionState' => $result['sessionState'],
        ...$debugFields,
    ], JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(['status' => $result['status'], ...$debugFields], JSON_UNESCAPED_SLASHES);
}
