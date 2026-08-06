<?php
declare(strict_types=1);

/**
 * Full raw dump of the store to JSON (plan §6, C-04 "extraction is a query
 * against the store"). No filtering — a live/consented-only variant and a
 * post-exhibition purge for declined sessions are noted as future work in
 * the plan, not built here.
 *
 * Usage: php bin/export.php [output-path]   (defaults to stdout)
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

$store = new Store(STORE_DB_PATH);

$export = [
    'exportedAt' => gmdate('Y-m-d\TH:i:s\Z'),
    'sessions' => $store->getAllSessions(),
    'exchanges' => $store->getAllExchanges(),
];

$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$outputPath = $argv[1] ?? null;
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
