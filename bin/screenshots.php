<?php
declare(strict_types=1);

/**
 * Screenshots every visitor-facing page, in fixed seeded states, for
 * reviewing visual changes (ADR 0018). Review aid only: nothing is compared
 * against a baseline and a visual change never fails anything.
 *
 * Runs the real app under `php -S` with LLM_PROVIDER=fake against a
 * throwaway database, seeded directly through Store (so a 16-turn session
 * doesn't trip the rate limiter), then captures each state by URL.
 * Phone pages are 390x844 (at 2x), the wall is 1920x1080 (plus the stadium
 * beamer profile at 1600x900). Writes the PNGs
 * plus an index.html gallery into output-dir (default: screenshots/,
 * gitignored).
 *
 * Backends:
 *   (default)     chrome-headless-shell. Not regular Chrome: its headless
 *                 mode won't lay out narrower than 500px, so "phone"
 *                 shots would really be 500px wide. Found via CHROME_BIN,
 *                 then PATH, then Playwright/Puppeteer caches. Install:
 *                 npx @puppeteer/browsers install chrome-headless-shell@stable
 *   --simulator   macOS + Xcode only: Safari in the booted iOS Simulator
 *                 (xcrun simctl), phone pages only. Boot one first, e.g.
 *                 `xcrun simctl boot "iPhone 16"`. There's no load event
 *                 to wait on, so each shot waits SIMULATOR_WAIT seconds
 *                 (default 4). Safari's own UI is part of the shot.
 *
 * Usage: php bin/screenshots.php [--simulator] [output-dir]
 */

if (php_sapi_name() !== 'cli') {
    exit(1);
}

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

$root = dirname(__DIR__);
$useSimulator = in_array('--simulator', $argv, true);
$positional = array_values(array_filter(array_slice($argv, 1), static fn ($a) => !str_starts_with($a, '--')));
$outDir = $positional[0] ?? "$root/screenshots";

const PHONE = [390, 844];
const WALL = [1920, 1080];
const BEAMER = [1600, 900]; // stadium profile: Epson EB-1945W as run at the exhibition

function fail(string $message): never
{
    fwrite(STDERR, "screenshots: $message\n");
    exit(1);
}

function find_headless_shell(): string
{
    $env = getenv('CHROME_BIN');
    if ($env !== false && $env !== '') {
        is_executable($env) || fail("CHROME_BIN is not executable: $env");
        return $env;
    }
    $onPath = trim((string) shell_exec('command -v chrome-headless-shell 2>/dev/null'));
    if ($onPath !== '') {
        return $onPath;
    }
    $home = getenv('HOME') ?: '';
    $candidates = [
        ...glob('/opt/pw-browsers/chromium_headless_shell-*/chrome-*/headless_shell') ?: [],
        ...glob("$home/.cache/ms-playwright/chromium_headless_shell-*/chrome-*/headless_shell") ?: [],
        ...glob("$home/Library/Caches/ms-playwright/chromium_headless_shell-*/chrome-*/headless_shell") ?: [],
        ...glob("$home/.cache/puppeteer/chrome-headless-shell/*/chrome-headless-shell-*/chrome-headless-shell") ?: [],
        ...glob(dirname(__DIR__) . "/chrome-headless-shell/*/chrome-headless-shell-*/chrome-headless-shell") ?: [],
    ];
    rsort($candidates); // newest version first
    foreach ($candidates as $c) {
        if (is_executable($c)) {
            return $c;
        }
    }
    fail("no chrome-headless-shell found. Install one with\n"
        . "  npx @puppeteer/browsers install chrome-headless-shell@stable\n"
        . "and point CHROME_BIN at the binary it prints (regular Chrome won't do, see this file's header).");
}

/** Runs a command (argv array, no shell), returns [exit code, combined output]. */
function run(array $cmd): array
{
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($proc), (string) $out];
}

/*
|--------------------------------------------------------------------------
| seed a throwaway store
|--------------------------------------------------------------------------
*/

$tmpDir = sys_get_temp_dir() . '/sparring_screenshots_' . bin2hex(random_bytes(4));
mkdir($tmpDir);
$dbPath = "$tmpDir/store.db";
$store = new Store($dbPath);

/** One displayable live session with $turns exchanges; returns its id. */
function seed_session(Store $store, string $title, array $contributions): string
{
    $session = $store->createSession('live');
    $store->recordConsentDecision($session['id'], true, true, true);
    foreach ($contributions as $i => $text) {
        $store->appendExchange($session['id'], $text, sprintf(
            '(fake) Turn %d. You said "%s". What would have to be true for the opposite to hold?',
            $i + 1,
            $text
        ));
    }
    $store->setScenario($session['id'], $contributions[0], 'first-contribution');
    $store->setTitle($session['id'], $title);
    return $session['id'];
}

$wallTopics = [
    'Invisible design' => 'Good design is invisible.',
    'Users and manuals' => 'Users never read instructions, so why write them?',
    'Beauty vs. function' => 'A beautiful interface is always a usable one.',
    'Designing for AI' => 'Designers will be replaced by generative tools within ten years.',
    'Ethics of nudging' => 'Dark patterns are fine as long as sales go up.',
    'Wicked problems' => 'Every design problem has one correct solution.',
];
foreach ($wallTopics as $title => $opener) {
    seed_session($store, $title, [$opener]);
}
$midSession = seed_session($store, 'Invisible design', [
    'Good design is invisible.',
    'If people notice the design, something went wrong.',
    'Fine, but a signpost should still blend into the street.',
]);
$completeSession = seed_session($store, 'Endurance round', array_map(
    static fn (int $n) => "Argument number $n: constraints make design better.",
    range(1, TURN_ALLOWANCE)
));
unset($store); // release the handle before the server opens the same file

/*
|--------------------------------------------------------------------------
| start the app
|--------------------------------------------------------------------------
*/

$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$base = "http://127.0.0.1:$port";

$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', 'public', 'public/index.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', "$tmpDir/server.log", 'w'], 2 => ['file', "$tmpDir/server.log", 'a']],
    $pipes,
    $root,
    [...getenv(), 'LLM_PROVIDER' => 'fake', 'STORE_DB_PATH' => $dbPath, 'FAKE_LLM_DELAY_MS' => '0']
);
register_shutdown_function(static function () use ($server, $tmpDir): void {
    proc_terminate($server);
    proc_close($server);
    // Recursive: each Chrome shot leaves a whole profile directory in here.
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($entries as $entry) {
        $entry->isDir() && !$entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }
    @rmdir($tmpDir);
});
for ($i = 0, $up = false; $i < 50 && !$up; $i++) {
    $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
    $up = $conn !== false;
    $up ? fclose($conn) : usleep(100_000);
}
$up || fail("php -S never came up on port $port");

/*
|--------------------------------------------------------------------------
| what to capture: [file name, path, viewport]
|--------------------------------------------------------------------------
*/

$shots = [
    ['start', '/', PHONE],
    ['dojo-new', '/dojo', PHONE], // fresh visitor: consent step
    ['dojo-mid-session', "/dojo?s=$midSession", PHONE],
    ['dojo-complete', "/dojo?s=$completeSession", PHONE],
    ['ses', '/ses', PHONE],
    ['philosophy', '/philosophy', PHONE],
    ['credits', '/credits', PHONE],
    ['privacy', '/privacy', PHONE],
    ['terms', '/terms', PHONE],
    ['start-de', '/?lang=de', PHONE],
    ['arena', '/arena', WALL],
    ['arena-stadium', '/arena?d=stadium', BEAMER],
];
if ($useSimulator) {
    $shots = array_values(array_filter($shots, static fn ($s) => $s[2] === PHONE)); // the wall is a projector, not a phone
}

is_dir($outDir) || mkdir($outDir, 0775, true) || fail("can't create $outDir");
foreach (glob("$outDir/*.png") ?: [] as $old) {
    unlink($old); // stale shots from a previous, different page list
}

/*
|--------------------------------------------------------------------------
| capture
|--------------------------------------------------------------------------
*/

if ($useSimulator) {
    PHP_OS_FAMILY === 'Darwin' || fail('--simulator needs macOS with Xcode');
    [$code, $out] = run(['xcrun', 'simctl', 'list', 'devices', 'booted']);
    ($code === 0 && str_contains($out, 'Booted')) || fail('no booted iOS Simulator, e.g. xcrun simctl boot "iPhone 16"');
    run(['xcrun', 'simctl', 'status_bar', 'booted', 'override', '--time', '9:41', '--batteryLevel', '100']);
    $wait = (int) (getenv('SIMULATOR_WAIT') ?: 4);
    $capture = static function (string $url, array $viewport, string $png) use ($wait): array {
        [$code, $out] = run(['xcrun', 'simctl', 'openurl', 'booted', $url]);
        if ($code !== 0) {
            return [$code, $out];
        }
        sleep($wait);
        return run(['xcrun', 'simctl', 'io', 'booted', 'screenshot', $png]);
    };
} else {
    $chrome = find_headless_shell();
    echo "browser: $chrome\n";
    $capture = static function (string $url, array $viewport, string $png) use ($chrome, $tmpDir): array {
        return run([
            $chrome,
            '--no-sandbox', // root in containers; harmless elsewhere
            '--disable-gpu',
            '--hide-scrollbars',
            "--user-data-dir=$tmpDir/profile-" . bin2hex(random_bytes(3)), // fresh storage per shot
            '--window-size=' . $viewport[0] . ',' . $viewport[1],
            '--force-device-scale-factor=' . ($viewport === PHONE ? 2 : 1),
            // Every stylesheet turns its transitions off under reduced motion,
            // so each page renders straight to its settled state. Without it,
            // entrance fades are caught half-way (virtual time advances JS
            // timers, not CSS animations).
            '--force-prefers-reduced-motion',
            '--virtual-time-budget=5000', // lets load-time JS and fetches settle
            "--screenshot=$png",
            $url,
        ]);
    };
}

foreach ($shots as [$name, $path, $viewport]) {
    $png = "$outDir/$name.png";
    // Explicit language on every shot: ?lang= sets a year-long locale cookie,
    // which Simulator Safari keeps between shots and runs (Chrome gets a
    // fresh profile per shot), and the device language would decide otherwise.
    $url = $base . $path . (str_contains($path, 'lang=') ? '' : (str_contains($path, '?') ? '&' : '?') . 'lang=en');
    [$code, $out] = $capture($url, $viewport, $png);
    if ($code !== 0 || !is_file($png) || filesize($png) === 0) {
        fail("capturing $path failed (exit $code):\n$out");
    }
    echo "  $name.png  $path\n";
}

/*
|--------------------------------------------------------------------------
| gallery
|--------------------------------------------------------------------------
*/

$backend = $useSimulator ? 'iOS Simulator' : 'chrome-headless-shell';
$figures = implode("\n", array_map(
    static fn ($s) => sprintf(
        '<figure class="%s"><img src="%s.png" alt="%s" loading="lazy"><figcaption>%s <code>%s</code></figcaption></figure>',
        $s[2] === PHONE ? 'phone' : 'wall',
        $s[0],
        htmlspecialchars($s[0]),
        htmlspecialchars($s[0]),
        htmlspecialchars($s[1])
    ),
    $shots
));
file_put_contents("$outDir/index.html", <<<HTML
<!doctype html>
<meta charset="utf-8">
<title>Sparring screenshots</title>
<style>
body { font: 14px system-ui, sans-serif; margin: 24px; background: #f4f4f4; }
.grid { display: flex; flex-wrap: wrap; gap: 24px; align-items: flex-start; }
figure { margin: 0; }
figure.phone img { width: 260px; }
figure.wall { flex-basis: 100%; }
figure.wall img { width: 100%; max-width: 1280px; }
img { display: block; border: 1px solid #ccc; background: #fff; }
figcaption { margin-top: 6px; color: #444; }
</style>
<h1>Sparring screenshots</h1>
<p>{$backend}, fake LLM provider, seeded throwaway store. Session IDs (and so aliases/avatars) differ per run.</p>
<div class="grid">
{$figures}
</div>
HTML);

echo count($shots) . " screenshots → $outDir/index.html\n";
