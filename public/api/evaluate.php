<?php
declare(strict_types=1);

/** End-of-session feedback dialog (TODO.md "Session Evaluation"). Always-skippable — see dojo.js. */

require __DIR__ . '/../../src/Store.php';

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

// Untrusted shape in, one of the known-good shapes out — same lenient idiom
// as resolve_opening_message()/contribute.php's replyToExchangeId: an
// unknown question key or an out-of-range value is dropped rather than
// failing the whole request, since the visitor answering some questions but
// not others (or a stale client sending an old question set) is expected,
// not an error.
$rawAnswers = is_array($body['answers'] ?? null) ? $body['answers'] : [];
$answers = [];
foreach (EVAL_QUESTIONS as $key) {
    $value = $rawAnswers[$key] ?? null;
    if (is_int($value) && $value >= 1 && $value <= EVAL_SCALE_SIZE) {
        $answers[$key] = $value;
    }
}

$feedback = is_string($body['feedback'] ?? null) ? trim(mb_substr($body['feedback'], 0, CONTRIBUTION_MAX_CHARS)) : null;

$store = new Store(STORE_DB_PATH);
$saved = $store->saveEvaluation($body['sessionId'], $answers, $feedback);

if (!$saved && $store->getSession($body['sessionId']) === null) {
    http_response_code(404);
    echo json_encode(['status' => 'session-unknown']);
    exit;
}

echo json_encode(['status' => 'ok']);
