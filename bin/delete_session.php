<?php
declare(strict_types=1);

/**
 * Deletes one session by id and everything under it — its exchanges and
 * its evaluation row. Destructive, so it refuses to run without --confirm;
 * --dry-run always wins and only reports what would go.
 *
 * Usage: php bin/delete_session.php <session-id> --dry-run
 *        php bin/delete_session.php <session-id> --confirm
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit(
        "Usage: php bin/delete_session.php <session-id> --dry-run\n" .
        "       php bin/delete_session.php <session-id> --confirm\n"
    );
}

$dryRun = in_array('--dry-run', $argv, true);
$confirmed = in_array('--confirm', $argv, true);

// session id is the first positional (non-flag) arg after the script name
$sessionId = null;
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--')) {
        $sessionId = $arg;
        break;
    }
}

if ($sessionId === null) {
    fwrite(STDERR, "usage: php bin/delete_session.php <session-id> --dry-run|--confirm\n");
    exit(1);
}

$store = new Store(STORE_DB_PATH);

if ($store->getSession($sessionId) === null) {
    fwrite(STDERR, "no session with id {$sessionId}\n");
    exit(1);
}

if ($dryRun) {
    $exchanges = count($store->getExchanges($sessionId));
    fwrite(STDOUT, sprintf(
        "dry run — would delete session %s and %d exchange(s)\n",
        $sessionId,
        $exchanges
    ));
    exit(0);
}

if (!$confirmed) {
    fwrite(STDERR, "refusing to delete without --confirm (pass --dry-run to preview first)\n");
    exit(1);
}

$deleted = $store->deleteSession($sessionId);
fwrite(STDOUT, sprintf(
    "deleted session %s and %d exchange(s)\n",
    $sessionId,
    $deleted['exchanges']
));
