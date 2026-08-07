<?php
declare(strict_types=1);

/**
 * Empties every table in the store. Destructive, so it refuses to run
 * without --confirm; --dry-run always wins and only reports counts.
 *
 * Usage: php bin/reset_db.php --dry-run
 *        php bin/reset_db.php --confirm
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

$dryRun = in_array('--dry-run', $argv, true);
$confirmed = in_array('--confirm', $argv, true);

$store = new Store(STORE_DB_PATH);

if ($dryRun) {
    $counts = $store->getCounts();
    fwrite(STDOUT, sprintf(
        "dry run — would delete %d session(s), %d exchange(s), %d rate-limit window(s)\n",
        $counts['sessions'],
        $counts['exchanges'],
        $counts['rateLimitWindows']
    ));
    exit(0);
}

if (!$confirmed) {
    fwrite(STDERR, "refusing to reset without --confirm (pass --dry-run to preview first)\n");
    exit(1);
}

$deleted = $store->resetAll();
fwrite(STDOUT, sprintf(
    "deleted %d session(s), %d exchange(s), %d rate-limit window(s)\n",
    $deleted['sessions'],
    $deleted['exchanges'],
    $deleted['rateLimitWindows']
));
