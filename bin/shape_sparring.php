<?php
declare(strict_types=1);

/**
 * Reply-shape metrics (stacked questions, sentence length, tics, uniformity)
 * for simulated transcripts or real sessions. Plain code: no API calls, no
 * cost, zero variance. Definitions live in evals/sparring/reply_shape.php.
 *
 * Usage:
 *   php bin/shape_sparring.php <iteration-dir> [<iteration-dir> ...]
 *   php bin/shape_sparring.php --db=data/store.db [--origin=study|live|pilot]
 *
 * Several arguments print one column each, so two iterations can be read
 * side by side. Baseline (no-prompt) transcripts are skipped. The --db form
 * opens the store read-only.
 */

require __DIR__ . '/../evals/sparring/reply_shape.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

$args = array_slice($argv, 1);
if ($args === [] || in_array('--help', $args, true) || in_array('-h', $args, true)) {
    exit("Usage: php bin/shape_sparring.php <iteration-dir>... | --db=<store.db> [--origin=study|live|pilot]\n");
}

$db = $origin = null;
$dirs = [];
foreach ($args as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $db = substr($arg, 5);
    } elseif (str_starts_with($arg, '--origin=')) {
        $origin = substr($arg, 9);
    } else {
        $dirs[] = rtrim($arg, '/');
    }
}

/** @return list<list<string>> */
function conversationsFromDir(string $dir): array
{
    if (!is_dir($dir)) {
        fwrite(STDERR, "No such directory: {$dir}\n");
        exit(1);
    }
    $paths = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getFilename() === 'transcript.json') {
            $paths[] = $file->getPathname();
        }
    }
    sort($paths);
    $conversations = [];
    foreach ($paths as $path) {
        $t = json_decode((string) file_get_contents($path), true);
        if (!is_array($t) || !empty($t['baseline']) || empty($t['exchanges'])) {
            continue;
        }
        $conversations[] = array_column($t['exchanges'], 'sparringResponse');
    }
    return $conversations;
}

/** @return list<list<string>> */
function conversationsFromDb(string $path, ?string $origin): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "No such file: {$path}\n");
        exit(1);
    }
    $pdo = new PDO('sqlite:file:' . $path . '?mode=ro', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sql = 'SELECT e.session_id, e.sparring_response FROM exchanges e JOIN sessions s ON s.id = e.session_id'
        . ($origin !== null ? ' WHERE s.origin = :origin' : '')
        . ' ORDER BY e.session_id, e.position';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($origin !== null ? ['origin' => $origin] : []);
    $bySession = [];
    foreach ($stmt as $row) {
        $bySession[$row['session_id']][] = $row['sparring_response'];
    }
    return array_values($bySession);
}

$columns = [];
if ($db !== null) {
    $columns[$origin !== null ? "db:{$origin}" : 'db:all'] = summarizeShape(conversationsFromDb($db, $origin));
}
foreach ($dirs as $dir) {
    $columns[basename($dir)] = summarizeShape(conversationsFromDir($dir));
}

$labels = array_keys($columns);
$rows = array_keys(reset($columns));
printf("%-34s", 'metric');
foreach ($labels as $l) {
    printf(" %18s", mb_substr($l, 0, 18));
}
echo "\n";
foreach ($rows as $row) {
    printf("%-34s", $row);
    foreach ($columns as $c) {
        printf(" %18s", $c[$row]);
    }
    echo "\n";
}
