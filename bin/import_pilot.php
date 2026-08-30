<?php
declare(strict_types=1);

/**
 * TI-05 / TF-06: operator seeds pilot material. Run on the host, never web-routed
 * (C-03) — this script has no HTTP entry point. Performs no generation and never
 * calls PE-01 (SE-03 UC-01 note): responses already exist in the transcripts.
 *
 * Usage: php bin/import_pilot.php [directory]   (defaults to config's PILOT_DATA_DIR)
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';
require __DIR__ . '/../src/scenario.php';

/** @return array{ok: bool, reason?: string, exchanges?: list<array{contribution: string, response: string}>, scenarioSourceText?: string, title?: ?string, replyTo?: ?string} */
function validate_transcript(mixed $data): array
{
    if (!is_array($data) || !isset($data['exchanges']) || !is_array($data['exchanges']) || $data['exchanges'] === []) {
        return ['ok' => false, 'reason' => "missing or empty 'exchanges' array"];
    }

    $exchanges = [];
    foreach ($data['exchanges'] as $i => $pair) {
        if (
            !is_array($pair)
            || !isset($pair['contribution'], $pair['response'])
            || !is_string($pair['contribution']) || trim($pair['contribution']) === ''
            || !is_string($pair['response']) || trim($pair['response']) === ''
        ) {
            return ['ok' => false, 'reason' => "exchange #$i missing a non-empty 'contribution' or 'response'"];
        }
        $exchanges[] = ['contribution' => trim($pair['contribution']), 'response' => trim($pair['response'])];
    }

    $scenarioSourceText = isset($data['scenario_source_text']) && is_string($data['scenario_source_text'])
        ? trim($data['scenario_source_text'])
        : $exchanges[0]['contribution'];

    // Title is optional — most transcripts rely on the arena's scenario
    // fallback (TF-05) — but when supplied it must be a non-empty string.
    $title = null;
    if (isset($data['title'])) {
        if (!is_string($data['title']) || trim($data['title']) === '') {
            return ['ok' => false, 'reason' => "'title' must be a non-empty string when present"];
        }
        $title = trim($data['title']);
    }

    // Optional: names another seed file (by filename stem, no .json) whose
    // displayed exchange this transcript is a reply to. import_directory()
    // resolves it and links this seed's first exchange, bumping the target's
    // reply_count so the wall renders its reply counter (TF-06). One level
    // only — a reply seed may not itself be a reply target.
    $replyTo = null;
    if (isset($data['reply_to'])) {
        if (!is_string($data['reply_to']) || trim($data['reply_to']) === '') {
            return ['ok' => false, 'reason' => "'reply_to' must be a non-empty string when present"];
        }
        $replyTo = trim($data['reply_to']);
    }

    return ['ok' => true, 'exchanges' => $exchanges, 'scenarioSourceText' => $scenarioSourceText, 'title' => $title, 'replyTo' => $replyTo];
}

function import_directory(Store $store, string $dir): array
{
    $imported = [];
    $rejected = [];

    $files = glob(rtrim($dir, '/') . '/*.json') ?: [];
    sort($files);

    // Parse and validate every file up front, keyed by filename stem, so a
    // reply seed can resolve its `reply_to` target regardless of glob order.
    // Two import passes then follow.
    $parsed = []; // stem => validated transcript + 'name'
    foreach ($files as $path) {
        $name = basename($path);
        $stem = basename($path, '.json');
        $raw = file_get_contents($path);
        if ($raw === false) {
            $rejected[] = ['file' => $name, 'reason' => 'unreadable'];
            continue;
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $rejected[] = ['file' => $name, 'reason' => 'invalid JSON: ' . json_last_error_msg()];
            continue;
        }

        $result = validate_transcript($data);
        if (!$result['ok']) {
            $rejected[] = ['file' => $name, 'reason' => $result['reason']];
            continue;
        }

        $parsed[$stem] = ['name' => $name] + $result;
    }

    // Pass 1: base seeds (no `reply_to`). These create the sessions a reply
    // seed points at, so record each one's displayed (latest) exchange — the
    // row a wall card and its QR code carry.
    $latestExchangeIdByStem = [];
    foreach ($parsed as $stem => $t) {
        if ($t['replyTo'] !== null) {
            continue;
        }
        $r = import_transcript($store, $t, null);
        $latestExchangeIdByStem[$stem] = $r['lastExchangeId'];
        $imported[] = ['file' => $t['name'], 'sessionId' => $r['sessionId'], 'exchangeCount' => $r['exchangeCount']];
    }

    // Pass 2: reply seeds. Link the seed's first exchange to the referenced
    // base seed's displayed exchange — appendExchange bumps that row's
    // reply_count in the same transaction (position-1 gate).
    foreach ($parsed as $stem => $t) {
        if ($t['replyTo'] === null) {
            continue;
        }
        if (!isset($latestExchangeIdByStem[$t['replyTo']])) {
            // Unknown stem, or one that is itself a reply seed — fail loud
            // rather than silently import a seed whose counter never renders.
            $rejected[] = ['file' => $t['name'], 'reason' => "reply_to references unknown base seed '{$t['replyTo']}'"];
            continue;
        }
        $r = import_transcript($store, $t, $latestExchangeIdByStem[$t['replyTo']]);
        $imported[] = ['file' => $t['name'], 'sessionId' => $r['sessionId'], 'exchangeCount' => $r['exchangeCount']];
    }

    return ['imported' => $imported, 'rejected' => $rejected];
}

/**
 * Writes one validated transcript as a pilot session. When $replyToExchangeId
 * is set, the first exchange (position 1) is linked to it, bumping that row's
 * reply_count exactly once (see Store::appendExchange).
 *
 * @param array{name: string, exchanges: list<array{contribution: string, response: string}>, scenarioSourceText: string, title: ?string, replyTo: ?string} $t
 * @return array{sessionId: string, lastExchangeId: int, exchangeCount: int}
 */
function import_transcript(Store $store, array $t, ?int $replyToExchangeId): array
{
    $session = $store->createSession('pilot');
    $lastExchangeId = 0;
    foreach ($t['exchanges'] as $i => $pair) {
        // Only the first turn counts as a QR reply — later turns pass null.
        $link = $i === 0 ? $replyToExchangeId : null;
        $exchange = $store->appendExchange($session['id'], $pair['contribution'], $pair['response'], $link);
        $lastExchangeId = $exchange['id'];
    }
    $scenario = derive_scenario_statement($t['scenarioSourceText']);
    $store->setScenario($session['id'], $scenario, 'first-contribution');
    if ($t['title'] !== null) {
        $store->setTitle($session['id'], $t['title']);
    }
    $store->setDisplayable($session['id'], true);
    $store->setConsent($session['id'], true);

    return [
        'sessionId' => $session['id'],
        'lastExchangeId' => $lastExchangeId,
        'exchangeCount' => count($t['exchanges']),
    ];
}

// --- entry point ---
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only (C-03)\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit("Usage: php bin/import_pilot.php [directory]   (defaults to config's PILOT_DATA_DIR)\n");
}

$dir = $argv[1] ?? PILOT_DATA_DIR;
$store = new Store(STORE_DB_PATH);
$outcome = import_directory($store, $dir);

printf("imported %d transcript(s) from %s\n", count($outcome['imported']), $dir);
foreach ($outcome['imported'] as $row) {
    printf("  ok   %-30s session=%s exchanges=%d\n", $row['file'], $row['sessionId'], $row['exchangeCount']);
}
foreach ($outcome['rejected'] as $row) {
    printf("  FAIL %-30s %s\n", $row['file'], $row['reason']);
}
if ($outcome['rejected'] !== []) {
    printf("%d file(s) rejected — see above\n", count($outcome['rejected']));
}
