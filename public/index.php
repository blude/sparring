<?php
declare(strict_types=1);

// Sole front controller — LocalValetDriver.php forces every Valet request
// through here (and this router does the same for `php -S`, see below),
// so this is the one place config.php needs to be loaded. Page scripts are
// no longer directly reachable by their own *.php URL.
$routes = [
    '/'                   => __DIR__ . '/start.php',
    '/input'              => __DIR__ . '/input.php',
    '/display'            => __DIR__ . '/display.php',
    '/credits'            => __DIR__ . '/credits.php',
    '/philosophy'         => __DIR__ . '/philosophy.php',
    '/privacy'            => __DIR__ . '/privacy.php',
    '/terms'              => __DIR__ . '/terms.php',
    '/sfx-debug'          => __DIR__ . '/sfx-debug.php',
    '/api/session'        => __DIR__ . '/api/session.php',
    '/api/session-state'  => __DIR__ . '/api/session_state.php',
    '/api/contribute'     => __DIR__ . '/api/contribute.php',
    '/api/display'        => __DIR__ . '/api/display.php',
    '/api/title'          => __DIR__ . '/api/title.php',
];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// php -S with a router script intercepts *every* request, including real
// static files (assets/*.js, favicon.ico, ...) — unlike Valet/nginx, which
// serve those before PHP ever runs. Returning false here hands it back to
// the built-in server's default static-file handling. Excludes .php so a
// direct /input.php-style request still falls through to the 404 below,
// matching LocalValetDriver.php's behavior. No-op under Valet.
$asFile = __DIR__ . $path;
if ($path !== '/' && is_file($asFile) && !str_ends_with($asFile, '.php')) {
    return false;
}

if (!isset($routes[$path])) {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/../config.php';
require $routes[$path];
