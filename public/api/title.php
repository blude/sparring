<?php
declare(strict_types=1);

/**
 * Generates (or reuses) a session's header title, out of band from the turn
 * that created its first exchange — see Sparring::generateAndStoreTitle().
 * Fired fire-and-forget from the client (input.js) once turn 1 succeeds, so
 * every failure mode here just means the client's existing raw-text
 * placeholder title stays on screen — never a blocked or broken UI.
 */

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../src/Store.php';
require __DIR__ . '/../../src/LlmClient.php';
require __DIR__ . '/../../src/Sparring.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method-not-allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body) || !isset($body['sessionId']) || !is_string($body['sessionId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'malformed-request']);
    exit;
}

$store = new Store(STORE_DB_PATH);
$session = $store->getSession($body['sessionId']);
if ($session === null) {
    http_response_code(404);
    echo json_encode(['error' => 'session-unknown']);
    exit;
}

$llm = new LlmClient();
$sparring = new Sparring($store, $llm);

try {
    $title = $sparring->generateAndStoreTitle($body['sessionId']);
} catch (RuntimeException) {
    // No exchange yet to derive a title from — the client only fires this
    // after turn 1 succeeds, so this shouldn't normally happen, but fail
    // soft either way (client ignores a non-'title' response body).
    http_response_code(409);
    echo json_encode(['error' => 'no-exchange']);
    exit;
}

echo json_encode(['title' => $title], JSON_UNESCAPED_SLASHES);
