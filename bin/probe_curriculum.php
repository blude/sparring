<?php
declare(strict_types=1);

/**
 * Manual retrieval probe: runs a free-text string (a made-up visitor
 * contribution, a scenario line, whatever) through Store::searchCurriculum()
 * against the real, already-imported curriculum_chunks table and prints
 * what would come back — path, BM25 score, snippet. Diagnostic only, no
 * write path; exists to sanity-check recall against realistic prompts
 * before curriculum retrieval is wired into actual generation (see
 * bin/import_curriculum.php's docblock — that wiring is a separate,
 * not-yet-built step).
 *
 * search() already tokenizes/sanitizes whatever string it's given (see
 * Store::sanitizeFtsQuery) — a whole sentence works the same as a single
 * word, just OR'd across every token in it, so this is a thin print wrapper,
 * not new matching logic.
 *
 * Usage: php bin/probe_curriculum.php "<free-text query>" [--limit=N]
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit("Usage: php bin/probe_curriculum.php \"<free-text query>\" [--limit=N]\n");
}

$limit = 5;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = (int) substr($arg, strlen('--limit='));
    }
}
// query is the first positional (non-flag) arg after the script name
$query = null;
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        $query = $arg;
        break;
    }
}
if ($query === null) {
    fwrite(STDERR, "error: no query given — php bin/probe_curriculum.php \"<free-text query>\"\n");
    exit(1);
}

$store = new Store(STORE_DB_PATH);
$hits = $store->searchCurriculum($query, $limit);

printf("query: %s\n", $query);
printf("%d hit(s)\n\n", count($hits));
foreach ($hits as $h) {
    printf("%-35s score=%6.2f\n  %s\n\n", $h['path'], $h['score'], $h['snippet']);
}
