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
 *   --markdown  One human-readable transcript per session (YAML frontmatter
 *               of session properties + heading + turns); each session's
 *               frontmatter fence doubles as the between-session rule.
 *   --index     One scannable line per session, fields joined by " - ".
 *               Default columns: id, date, title, exchanges. Override with
 *               --columns=<csv> from: id date datetime title exchanges
 *               origin turns lastactive displayable.
 *
 * Usage: php bin/export.php [--consented-only] [output-path]
 *        php bin/export.php --jsonl|--csv|--markdown [--session=<id>] [--consented-only] [output-path]
 *        php bin/export.php --index [--columns=<csv>] [--consented-only] [output-path]
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
        "       php bin/export.php --jsonl|--csv|--markdown [--session=<id>] [--consented-only] [output-path]\n" .
        "       php bin/export.php --index [--columns=<csv>] [--consented-only] [output-path]\n"
    );
}

/**
 * --index mode's row builder, pulled out so it's unit-testable
 * (tests/smoke_domain.php). $exchangeCounts maps session id -> exchange
 * count. $columnsCsv is the raw --columns= value (null = the default set).
 * Throws InvalidArgumentException naming the bad key on an unknown column.
 */
function render_session_index(array $sessions, array $exchangeCounts, ?string $columnsCsv): string
{
    $available = [
        'id'          => fn(array $s) => $s['id'],
        'date'        => fn(array $s) => substr($s['createdAt'], 0, 10),
        'datetime'    => fn(array $s) => $s['createdAt'],
        'title'       => fn(array $s) => $s['title'] ?? '(untitled)',
        'exchanges'   => function (array $s) use ($exchangeCounts) {
            $n = $exchangeCounts[$s['id']] ?? 0;
            return $n . ' exchange' . ($n === 1 ? '' : 's');
        },
        'origin'      => fn(array $s) => $s['origin'],
        'turns'       => fn(array $s) => (string) $s['turnCount'],
        'lastactive'  => fn(array $s) => $s['lastActiveAt'],
        'displayable' => fn(array $s) => $s['displayable'] ? 'yes' : 'no',
    ];

    $columns = $columnsCsv === null || $columnsCsv === ''
        ? ['id', 'date', 'title', 'exchanges']
        : array_map('trim', explode(',', $columnsCsv));

    foreach ($columns as $col) {
        if (!isset($available[$col])) {
            throw new InvalidArgumentException(
                "unknown --columns key '{$col}' — valid: " . implode(' ', array_keys($available))
            );
        }
    }

    $lines = array_map(
        fn(array $s) => implode(' - ', array_map(fn(string $col) => $available[$col]($s), $columns)),
        $sessions
    );
    return implode("\n", $lines) . ($lines === [] ? '' : "\n");
}

$store = new Store(STORE_DB_PATH);

$jsonl = in_array('--jsonl', $argv, true);
$csv = in_array('--csv', $argv, true);
$markdown = in_array('--markdown', $argv, true);
$index = in_array('--index', $argv, true);
$consentedOnly = in_array('--consented-only', $argv, true);
$sessionId = null;
$columnsCsv = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--session=')) {
        $sessionId = substr($arg, strlen('--session='));
    }
    if (str_starts_with($arg, '--columns=')) {
        $columnsCsv = substr($arg, strlen('--columns='));
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
        $lines = [];
        if ($session !== null) {
            // Session properties as YAML frontmatter. Each value is JSON-encoded,
            // which is valid YAML for scalars (strings quoted, bool/null/int bare)
            // and safely handles colons/quotes/newlines in free-text fields like
            // scenario — no YAML extension needed.
            $lines[] = '---';
            foreach ($session as $key => $value) {
                $lines[] = $key . ': ' . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $lines[] = '---';
            $lines[] = '';
        }
        $lines[] = "# Session {$sid}";
        $lines[] = '';
        foreach ($exchanges as $e) {
            $lines[] = "**Visitor:** {$e['visitorContribution']}";
            $lines[] = '';
            $lines[] = "**Sparring:** {$e['sparringResponse']}";
            $lines[] = '';
        }
        $docs[] = implode("\n", $lines);
    }
    // Each doc opens with its own `---` frontmatter fence, which doubles as the
    // between-session rule — no extra separator needed.
    $out = implode("\n", $docs);
    $writeOut($out, $outputPath, sprintf("exported %d session(s) to %s\n", count($docs), $outputPath ?? ''));
    exit;
}

if ($index) {
    $sessions = $allSessions ?? $store->getAllSessions();
    if ($consentedIds !== null) {
        $sessions = array_values(array_filter($sessions, fn(array $s) => in_array($s['id'], $consentedIds, true)));
    }
    // One pass over all exchanges to count per session — cheaper than a query per row.
    $exchangeCounts = [];
    foreach ($store->getAllExchanges() as $e) {
        $exchangeCounts[$e['sessionId']] = ($exchangeCounts[$e['sessionId']] ?? 0) + 1;
    }
    try {
        $out = render_session_index($sessions, $exchangeCounts, $columnsCsv);
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    $writeOut($out, $outputPath, sprintf("listed %d session(s) to %s\n", count($sessions), $outputPath ?? ''));
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
