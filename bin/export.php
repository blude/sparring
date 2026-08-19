<?php
declare(strict_types=1);

/**
 * Full raw dump of the store to JSON (plan §6, C-04 "extraction is a query
 * against the store"). No filtering — a live/consented-only variant and a
 * post-exhibition purge for declined sessions are noted as future work in
 * the plan, not built here.
 *
 * --jsonl [--session=<id>] switches to one exchange-per-line JSONL, optionally
 * scoped to a single session (e.g. piping one visitor's transcript elsewhere).
 * Sessions metadata is dropped in this mode — it doesn't fit a flat exchange
 * stream, and the caller already knows the session_id it asked for.
 *
 * Usage: php bin/export.php [output-path]                     (full JSON dump)
 *        php bin/export.php --jsonl [--session=<id>] [output-path]
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit(
        "Usage: php bin/export.php [output-path]                     (full JSON dump)\n" .
        "       php bin/export.php --jsonl [--session=<id>] [output-path]\n"
    );
}

$store = new Store(STORE_DB_PATH);

$jsonl = in_array('--jsonl', $argv, true);
$sessionId = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--session=')) {
        $sessionId = substr($arg, strlen('--session='));
    }
}
// output path is the first positional (non-flag) arg after the script name
$outputPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        $outputPath = $arg;
        break;
    }
}

if ($jsonl) {
    $exchanges = $sessionId === null ? $store->getAllExchanges() : $store->getExchanges($sessionId);
    // OpenAI fine-tuning chat format: one {"messages": [...]} line per exchange.
    $lines = array_map(
        fn(array $e) => json_encode([
            'messages' => [
                ['role' => 'user', 'content' => $e['visitorContribution']],
                ['role' => 'assistant', 'content' => $e['sparringResponse']],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $exchanges
    );
    $out = implode("\n", $lines) . ($lines === [] ? '' : "\n");

    if ($outputPath === null) {
        fwrite(STDOUT, $out);
    } else {
        file_put_contents($outputPath, $out);
        fwrite(STDERR, sprintf("exported %d exchange(s) to %s\n", count($exchanges), $outputPath));
    }
    exit;
}

$export = [
    'exportedAt' => gmdate('Y-m-d\TH:i:s\Z'),
    'sessions' => $store->getAllSessions(),
    'exchanges' => $store->getAllExchanges(),
];

$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($outputPath === null) {
    fwrite(STDOUT, $json . "\n");
} else {
    file_put_contents($outputPath, $json);
    fwrite(STDERR, sprintf(
        "exported %d session(s), %d exchange(s) to %s\n",
        count($export['sessions']),
        count($export['exchanges']),
        $outputPath
    ));
}
