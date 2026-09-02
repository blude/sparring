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
 * --jsonl/--csv/--markdown [--session=<id>] switch to one alternate export
 * format each, optionally scoped to a single session (--session=<id>) and/or
 * --consented-only. Mutually exclusive; --jsonl wins if more than one given.
 *
 *   --jsonl     One conversation-per-line JSONL in the OpenAI fine-tuning
 *               chat format ({"messages": [...]}), each exchange in a
 *               session flattened to a user/assistant turn pair.
 *               --session=<id> yields one line; omitted, one line per
 *               session. Session metadata is dropped — doesn't fit the
 *               schema.
 *   --csv       Flat exchange rows (id, sessionId, visitorContribution,
 *               sparringResponse, position, createdAt), one per line.
 *   --markdown  One human-readable transcript per session (heading +
 *               metadata + turns), sessions separated by a rule.
 *
 * Usage: php bin/export.php [--consented-only] [output-path]
 *        php bin/export.php --jsonl|--csv|--markdown [--session=<id>] [--consented-only] [output-path]
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit(
        "Usage: php bin/export.php [--consented-only] [output-path]\n" .
        "       php bin/export.php --jsonl|--csv|--markdown [--session=<id>] [--consented-only] [output-path]\n"
    );
}

$store = new Store(STORE_DB_PATH);

$jsonl = in_array('--jsonl', $argv, true);
$csv = in_array('--csv', $argv, true);
$markdown = in_array('--markdown', $argv, true);
$consentedOnly = in_array('--consented-only', $argv, true);
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

// $allSessions is fetched lazily — only when --consented-only, --markdown, or
// the full (non-alternate-format) dump actually needs session rows.
$allSessions = null;
$consentedIds = null;
if ($consentedOnly) {
    $allSessions = $store->getAllSessions();
    // Sessions where the visitor explicitly granted retention consent (null = undecided, excluded too).
    $consentedIds = array_column(array_filter($allSessions, fn(array $s) => $s['consentGranted'] === true), 'id');
}

// Shared by --jsonl/--csv/--markdown: the exchange rows those formats all draw from.
$filteredExchanges = function () use ($store, $sessionId, $consentedIds): array {
    $exchanges = $sessionId === null ? $store->getAllExchanges() : $store->getExchanges($sessionId);
    if ($consentedIds !== null) {
        $exchanges = array_values(array_filter($exchanges, fn(array $e) => in_array($e['sessionId'], $consentedIds, true)));
    }
    return $exchanges;
};

$writeOut = function (string $out, ?string $outputPath = null, ?string $countMessage = null): void {
    if ($outputPath === null) {
        fwrite(STDOUT, $out);
    } else {
        file_put_contents($outputPath, $out);
        if ($countMessage !== null) {
            fwrite(STDERR, $countMessage);
        }
    }
};

if ($jsonl) {
    // Group into one ordered turn-list per session. getAllExchanges()/getExchanges()
    // are already ordered by (session_id,) position, so a plain bucket preserves it.
    $bySession = [];
    foreach ($filteredExchanges() as $e) {
        $bySession[$e['sessionId']][] = ['role' => 'user', 'content' => $e['visitorContribution']];
        $bySession[$e['sessionId']][] = ['role' => 'assistant', 'content' => $e['sparringResponse']];
    }

    // OpenAI fine-tuning chat format: one {"messages": [...]} line per session.
    $lines = array_map(
        fn(array $messages) => json_encode(['messages' => $messages], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        array_values($bySession)
    );
    $out = implode("\n", $lines) . ($lines === [] ? '' : "\n");
    $writeOut($out, $outputPath, sprintf("exported %d session(s) to %s\n", count($bySession), $outputPath ?? ''));
    exit;
}

if ($csv) {
    $exchanges = $filteredExchanges();
    $handle = fopen($outputPath ?? 'php://stdout', 'w');
    fputcsv($handle, ['id', 'sessionId', 'visitorContribution', 'sparringResponse', 'position', 'createdAt'], ',', '"', '\\');
    foreach ($exchanges as $e) {
        fputcsv($handle, [$e['id'], $e['sessionId'], $e['visitorContribution'], $e['sparringResponse'], $e['position'], $e['createdAt']], ',', '"', '\\');
    }
    fclose($handle);
    if ($outputPath !== null) {
        fwrite(STDERR, sprintf("exported %d exchange(s) to %s\n", count($exchanges), $outputPath));
    }
    exit;
}

if ($markdown) {
    $sessionsById = array_column($allSessions ?? $store->getAllSessions(), null, 'id');

    $bySession = [];
    foreach ($filteredExchanges() as $e) {
        $bySession[$e['sessionId']][] = $e;
    }

    $docs = [];
    foreach ($bySession as $sid => $exchanges) {
        $session = $sessionsById[$sid] ?? null;
        $lines = ["# Session {$sid}", ''];
        if ($session !== null) {
            $lines[] = '- title: ' . ($session['title'] ?? '(none)');
            $lines[] = "- origin: {$session['origin']}";
            $lines[] = '- scenario: ' . ($session['scenario'] ?? '(none)');
            $lines[] = "- created: {$session['createdAt']}";
            $lines[] = '';
        }
        foreach ($exchanges as $e) {
            $lines[] = "**Visitor:** {$e['visitorContribution']}";
            $lines[] = '';
            $lines[] = "**Sparring:** {$e['sparringResponse']}";
            $lines[] = '';
        }
        $docs[] = implode("\n", $lines);
    }
    $out = implode("\n---\n\n", $docs);
    $writeOut($out, $outputPath, sprintf("exported %d session(s) to %s\n", count($docs), $outputPath ?? ''));
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
