<?php
declare(strict_types=1);

/** TI-04 — Grant a session another allowance of turns after it hit the limit (SA-01-8). */

require __DIR__ . '/../../src/Store.php';
require __DIR__ . '/../../src/Sparring.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method-not-allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body) || !isset($body['sessionId']) || !is_string($body['sessionId']) || $body['sessionId'] === '') {
    http_response_code(400);
    echo json_encode(['error' => 'malformed-request']);
    exit;
}

$store = new Store(STORE_DB_PATH);
$sparring = new Sparring($store); // no LLM / rate limiter — extendSession touches neither

$result = $sparring->extendSession($body['sessionId']);
http_response_code($result['status'] === 'ok' ? 200 : 404); // only outcomes are 'ok' and 'session-unknown'
echo json_encode($result, JSON_UNESCAPED_SLASHES);
