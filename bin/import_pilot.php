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

/** @return array{ok: bool, reason?: string, exchanges?: list<array{contribution: string, response: string}>, scenarioSourceText?: string} */
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

    return ['ok' => true, 'exchanges' => $exchanges, 'scenarioSourceText' => $scenarioSourceText];
}

function import_directory(Store $store, string $dir): array
{
    $imported = [];
    $rejected = [];

    $files = glob(rtrim($dir, '/') . '/*.json') ?: [];
    sort($files);

    foreach ($files as $path) {
        $name = basename($path);
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

        $session = $store->createSession('pilot');
        foreach ($result['exchanges'] as $pair) {
            $store->appendExchange($session['id'], $pair['contribution'], $pair['response']);
        }
        $scenario = derive_scenario_statement($result['scenarioSourceText']);
        $store->setScenario($session['id'], $scenario, 'first-contribution');
        $store->setDisplayable($session['id'], true);
        $store->setConsent($session['id'], true);

        $imported[] = ['file' => $name, 'sessionId' => $session['id'], 'exchangeCount' => count($result['exchanges'])];
    }

    return ['imported' => $imported, 'rejected' => $rejected];
}

// --- entry point ---
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only (C-03)\n");
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
