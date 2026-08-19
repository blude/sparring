<?php
declare(strict_types=1);

/**
 * Full raw dump of the store to JSON (plan §6, C-04 "extraction is a query
 * against the store"). Unfiltered by default; --consented-only restricts to
 * sessions where the visitor's retention consent (E-01.7, TI-01) is true —
 * excludes declined *and* undecided sessions, not just declined ones, since
 * an analysis export shouldn't include sessions nobody opted into. A
 * post-exhibition purge for declined sessions is separate future work, not
 * built here.
 *
 * --jsonl [--session=<id>] switches to one conversation-per-line JSONL in the
 * OpenAI fine-tuning chat format ({"messages": [...]}), each exchange in a
 * session flattened to a user/assistant turn pair. --session=<id> scopes to
 * one session (one line out); omitted, every session gets its own line.
 * Sessions metadata is dropped in this mode — it doesn't fit the schema.
 *
 * Usage: php bin/export.php [--consented-only] [output-path]                     (full JSON dump)
 *        php bin/export.php --jsonl [--session=<id>] [--consented-only] [output-path]
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit(
        "Usage: php bin/export.php [--consented-only] [output-path]                     (full JSON dump)\n" .
        "       php bin/export.php --jsonl [--session=<id>] [--consented-only] [output-path]\n"
    );
}

$store = new Store(STORE_DB_PATH);

$jsonl = in_array('--jsonl', $argv, true);
$consentedOnly = in_array('--consented-only', $argv, true);
$sessionId = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--session=')) {
        $sessionId = substr($arg, strlen('--session='));
    }
}

// $allSessions is fetched lazily below — only when --consented-only or the full
// (non-jsonl) dump actually needs it, not on every plain --jsonl call.
$allSessions = null;
$consentedIds = null;
if ($consentedOnly) {
    $allSessions = $store->getAllSessions();
    // Sessions where the visitor explicitly granted retention consent (null = undecided, excluded too).
    $consentedIds = array_column(array_filter($allSessions, fn(array $s) => $s['consentGranted'] === true), 'id');
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
    if ($consentedIds !== null) {
        $exchanges = array_values(array_filter($exchanges, fn(array $e) => in_array($e['sessionId'], $consentedIds, true)));
    }

    // Group into one ordered turn-list per session. getAllExchanges()/getExchanges()
    // are already ordered by (session_id,) position, so a plain bucket preserves it.
    $bySession = [];
    foreach ($exchanges as $e) {
        $bySession[$e['sessionId']][] = ['role' => 'user', 'content' => $e['visitorContribution']];
        $bySession[$e['sessionId']][] = ['role' => 'assistant', 'content' => $e['sparringResponse']];
    }

    // OpenAI fine-tuning chat format: one {"messages": [...]} line per session.
    $lines = array_map(
        fn(array $messages) => json_encode(['messages' => $messages], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        array_values($bySession)
    );
    $out = implode("\n", $lines) . ($lines === [] ? '' : "\n");

    if ($outputPath === null) {
        fwrite(STDOUT, $out);
    } else {
        file_put_contents($outputPath, $out);
        fwrite(STDERR, sprintf("exported %d session(s) to %s\n", count($bySession), $outputPath));
    }
    exit;
}

$sessions = $allSessions ?? $store->getAllSessions();
$exchanges = $store->getAllExchanges();
if ($consentedIds !== null) {
    $sessions = array_values(array_filter($sessions, fn(array $s) => in_array($s['id'], $consentedIds, true)));
    $exchanges = array_values(array_filter($exchanges, fn(array $e) => in_array($e['sessionId'], $consentedIds, true)));
}

$export = [
    'exportedAt' => gmdate('Y-m-d\TH:i:s\Z'),
    'sessions' => $sessions,
    'exchanges' => $exchanges,
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
