<?php
declare(strict_types=1);

/**
 * Deletes sessions with zero exchanges older than a threshold — a visitor
 * loaded the page and never sent a turn (bot, reload, walk-away). Never
 * touches a session with any exchange, so a visitor mid-conversation is
 * never at risk. Destructive, so it refuses to run without --confirm;
 * --dry-run always wins and only reports the count.
 *
 * Usage: php bin/prune_orphaned_sessions.php [hours] --dry-run
 *        php bin/prune_orphaned_sessions.php [hours] --confirm
 *        (hours defaults to 24)
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit(
        "Usage: php bin/prune_orphaned_sessions.php [hours] --dry-run\n" .
        "       php bin/prune_orphaned_sessions.php [hours] --confirm\n" .
        "       (hours defaults to 24)\n"
    );
}

$dryRun = in_array('--dry-run', $argv, true);
$confirmed = in_array('--confirm', $argv, true);

$hoursArg = $argv[1] ?? null;
$hours = ($hoursArg !== null && ctype_digit($hoursArg)) ? (int) $hoursArg : 24;
$olderThanSeconds = $hours * 3600;

$store = new Store(STORE_DB_PATH);

if ($dryRun) {
    $count = $store->countOrphanedSessions($olderThanSeconds);
    fwrite(STDOUT, sprintf(
        "dry run — would delete %d orphaned session(s) (zero exchanges, inactive %dh+)\n",
        $count,
        $hours
    ));
    exit(0);
}

if (!$confirmed) {
    fwrite(STDERR, "refusing to prune without --confirm (pass --dry-run to preview first)\n");
    exit(1);
}

$deleted = $store->pruneOrphanedSessions($olderThanSeconds);
fwrite(STDOUT, sprintf("deleted %d orphaned session(s) (zero exchanges, inactive %dh+)\n", $deleted, $hours));
