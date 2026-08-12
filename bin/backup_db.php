<?php
declare(strict_types=1);

/**
 * Consistent-snapshot backup of the store, via Store::backupTo (SQLite's own
 * VACUUM INTO — correct even under WAL journal mode).
 *
 * Usage: php bin/backup_db.php [output-path]   (defaults to data/backups/store-<timestamp>.db)
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit("Usage: php bin/backup_db.php [output-path]   (defaults to data/backups/store-<timestamp>.db)\n");
}

$outputPath = $argv[1] ?? null;
if ($outputPath === null) {
    $backupDir = __DIR__ . '/../data/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0775, true);
    }
    $outputPath = $backupDir . '/store-' . gmdate('Ymd-His') . '.db';
}

$store = new Store(STORE_DB_PATH);
$store->backupTo($outputPath);

fwrite(STDERR, sprintf(
    "backed up store to %s (%d bytes)\n",
    $outputPath,
    filesize($outputPath)
));
