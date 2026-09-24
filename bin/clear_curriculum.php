<?php
declare(strict_types=1);

/**
 * Empties curriculum_chunks (poor woman's RAG ingestion, see
 * bin/import_curriculum.php) without touching data/curriculum/*.md or any
 * other table — the corpus is a disk-derived cache (see Store.php's comment
 * above replaceCurriculumChunks()), so clearing it is always safe to undo by
 * re-running bin/import_curriculum.php.
 *
 * Usage: php bin/clear_curriculum.php
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit("Usage: php bin/clear_curriculum.php\nEmpties curriculum_chunks. Re-run bin/import_curriculum.php to refill it.\n");
}

$store = new Store(STORE_DB_PATH);
$counts = $store->replaceCurriculumChunks([]);

printf("curriculum_chunks: %d -> %d\n", $counts['before'], $counts['after']);
