<?php
declare(strict_types=1);

/**
 * End-to-end check of the full request flow: starts the real app under
 * `php -S` (router included) with LLM_PROVIDER=fake and a throwaway
 * STORE_DB_PATH, then drives it over HTTP the way dojo.js/arena.js do.
 * Covers the public pages rendering, session open + consent, a turn, the
 * title call, session state, the wall feed, and each non-ok contribute path
 * the fake can trigger. No API key, no network beyond localhost.
 * Run: php -d zend.assertions=1 tests/smoke_http.php
 */

// assert() is compiled out under zend.assertions=-1 (production php.ini):
// refuse to run rather than pass without checking anything.
if (ini_get('zend.assertions') !== '1') {
    fwrite(STDERR, basename(__FILE__) . ": needs php -d zend.assertions=1 (tests/run.sh sets it)\n");
    exit(1);
}

require __DIR__ . '/../config.php'; // TURN_ALLOWANCE only
require __DIR__ . '/../src/FakeLlmClient.php'; // RESPONSE_PREFIX only

$root = dirname(__DIR__);
$tmpDir = sys_get_temp_dir() . '/sparring_smoke_http_' . bin2hex(random_bytes(4));
mkdir($tmpDir);

// Ask the OS for a free port, then hand it to php -S.
$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$base = "http://127.0.0.1:$port";

$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', 'public', 'public/index.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', "$tmpDir/server.log", 'w'], 2 => ['file', "$tmpDir/server.log", 'a']],
    $pipes,
    $root,
    // Explicit env wins over .env (config.php only fills gaps), so a real
    // provider or DB configured there can't leak into the test.
    [...getenv(), 'LLM_PROVIDER' => 'fake', 'STORE_DB_PATH' => "$tmpDir/store.db", 'FAKE_LLM_DELAY_MS' => '0']
);
assert(is_resource($server));

register_shutdown_function(static function () use ($server, $tmpDir): void {
    proc_terminate($server);
    proc_close($server);
    foreach (glob("$tmpDir/*") ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmpDir);
});

// Wait for the server to accept connections (normally well under a second).
for ($i = 0; $i < 50; $i++) {
    $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
    if ($conn !== false) {
        fclose($conn);
        break;
    }
    usleep(100_000);
}
assert($conn !== false, "php -S never came up on port $port");

/** @return array{0: int, 1: mixed} [status, decoded JSON body (or raw string if not JSON)] */
function request(string $method, string $path, ?array $json = null): array
{
    global $base;
    $opts = ['method' => $method, 'ignore_errors' => true, 'timeout' => 10];
    if ($json !== null) {
        $opts['header'] = "Content-Type: application/json\r\n";
        $opts['content'] = json_encode($json);
    }
    $body = file_get_contents($base . $path, false, stream_context_create(['http' => $opts]));
    preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m);
    $decoded = json_decode((string) $body, true);
    return [(int) ($m[1] ?? 0), $decoded ?? $body];
}

/*
|--------------------------------------------------------------------------
| pages render through the router
|--------------------------------------------------------------------------
*/

foreach (['/', '/dojo', '/arena', '/ses'] as $page) {
    [$status] = request('GET', $page);
    assert($status === 200, "GET $page → $status");
}
[$status] = request('GET', '/no-such-page');
assert($status === 404);

/*
|--------------------------------------------------------------------------
| session open → consent → turn → title → state → wall
|--------------------------------------------------------------------------
*/

[$status, $session] = request('POST', '/api/session', []);
assert($status === 200);
$sessionId = $session['sessionId'];
assert(is_string($sessionId) && $sessionId !== '');
assert($session['sessionState'] === 'awaiting-decision');

[$status, $session] = request('POST', '/api/session', [
    'sessionId' => $sessionId, 'tosAgreed' => true, 'retentionGranted' => true, 'projectionGranted' => true,
]);
assert($status === 200);
assert($session['sessionState'] === 'open');

$contribution = 'Good design is invisible.';
[$status, $turn] = request('POST', '/api/contribute', ['sessionId' => $sessionId, 'contribution' => $contribution]);
assert($status === 200, "contribute → $status");
assert($turn['status'] === 'ok');
assert($turn['exchange']['visitorContribution'] === $contribution);
assert(str_starts_with($turn['exchange']['sparringResponse'], FakeLlmClient::RESPONSE_PREFIX));
assert($turn['turnsRemaining'] === TURN_ALLOWANCE - 1);

[$status, $title] = request('POST', '/api/title', ['sessionId' => $sessionId]);
assert($status === 200);
assert(str_starts_with($title['title'], FakeLlmClient::RESPONSE_PREFIX));

[$status, $state] = request('GET', '/api/session-state?sessionId=' . urlencode($sessionId));
assert($status === 200);
assert(count($state['exchanges']) === 1);
assert($state['exchanges'][0]['sparringResponse'] === $turn['exchange']['sparringResponse']);

[$status, $wall] = request('GET', '/api/recent-exchanges');
assert($status === 200);
assert($wall['exchangeCount'] === 1);
assert(str_contains(json_encode($wall['items']), $contribution)); // projection granted → on the wall

/*
|--------------------------------------------------------------------------
| non-ok contribute paths (TI-02's error cases)
|--------------------------------------------------------------------------
*/

$contribute = static fn (string $text, ?string $sid = null): array
    => request('POST', '/api/contribute', ['sessionId' => $sid ?? $sessionId, 'contribution' => $text]);

[$status, $r] = $contribute('my number is [fake:personal]');
assert($status === 422 && $r['status'] === 'content-flagged' && $r['moderationReason'] === 'contains-personal-information');

[$status, $r] = $contribute('[fake:classify-error]');
assert($status === 422 && $r['moderationReason'] === 'llm-classification'); // moderation fails closed

[$status, $r] = $contribute('[fake:generation-error]');
assert($status === 502 && $r['status'] === 'generation-failed');

[$status, $r] = $contribute('hello', 'no-such-session');
assert($status === 404 && $r['status'] === 'session-unknown');

[$status] = request('POST', '/api/contribute', ['sessionId' => $sessionId]); // no contribution
assert($status === 400);

// None of the failed attempts above became an exchange.
[, $state] = request('GET', '/api/session-state?sessionId=' . urlencode($sessionId));
assert(count($state['exchanges']) === 1);

echo "smoke_http: ok\n";
