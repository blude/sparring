<?php
declare(strict_types=1);

/**
 * TI-04 — Retrieve display material. No parameters, by design (SE-02's TO-01
 * takes none either): item count, ordering, exchange selection, and pilot
 * fallback are all decided in Sparring::assembleDisplayMaterial, so a change to
 * any of them needs no change in SE-02 and no coordination between the two.
 */

require __DIR__ . '/../../src/Store.php';
require __DIR__ . '/../../src/Sparring.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method-not-allowed']);
    exit;
}

$store = new Store(STORE_DB_PATH);
$sparring = new Sparring($store);

$counts = $store->getCounts();
$display = resolve_display_config(); // ?d=<name> — item count is a display policy, so it's honoured here too

echo json_encode([
    'items' => $sparring->assembleDisplayMaterial($display['itemLimit']),
    'sessionCount' => $counts['sessions'],
    'exchangeCount' => $counts['exchanges'],
], JSON_UNESCAPED_SLASHES);
