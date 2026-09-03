<?php
declare(strict_types=1);

// Sole front controller — LocalValetDriver.php forces every Valet request
// through here (and this router does the same for `php -S`, see below),
// so this is the one place config.php needs to be loaded. Page scripts are
// no longer directly reachable by their own *.php URL.
$routes = [
    '/'                     => __DIR__ . '/start.php',
    '/dojo'                 => __DIR__ . '/dojo.php',
    '/arena'                => __DIR__ . '/arena.php',
    '/credits'              => __DIR__ . '/credits.php',
    '/philosophy'           => __DIR__ . '/philosophy.php',
    '/privacy'              => __DIR__ . '/privacy.php',
    '/terms'                => __DIR__ . '/terms.php',
    '/sfx-debug'            => __DIR__ . '/sfx-debug.php',
    '/api/session'          => __DIR__ . '/api/session.php',
    '/api/session-state'    => __DIR__ . '/api/session-state.php',
    '/api/contribute'       => __DIR__ . '/api/contribute.php',
    '/api/extend-session'   => __DIR__ . '/api/extend-session.php',
    '/api/recent-exchanges' => __DIR__ . '/api/recent-exchanges.php',
    '/api/title'            => __DIR__ . '/api/title.php',
    '/api/evaluate'         => __DIR__ . '/api/evaluate.php',
];

require_once __DIR__ . '/../config.php';

// Resolved once, up front, for every request (page or /api/*) — this is
// what actually sends the locale cookie on an explicit ?lang= override, and
// it must happen before any output. Later calls to resolve_locale()/t() in
// the same request reuse this memoized result.
resolve_locale();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Trailing slash isn't a distinct route ($routes only has the bare form) —
// redirect to the canonical slash-less form rather than serving the same
// page at both URLs (avoids duplicate content at two URLs for one page).
if ($path !== '/' && str_ends_with($path, '/')) {
    $canonical = rtrim($path, '/');
    if (isset($routes[$canonical])) {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . $canonical . ($qs !== '' ? "?$qs" : ''), true, 301);
        exit;
    }
}

// php -S with a router script intercepts *every* request, including real
// static files (assets/*.js, favicon.ico, ...) — unlike Valet/nginx, which
// serve those before PHP ever runs. Returning false here hands it back to
// the built-in server's default static-file handling. Excludes .php so a
// direct /dojo.php-style request still falls through to the 404 below,
// matching LocalValetDriver.php's behavior. No-op under Valet.
$asFile = __DIR__ . $path;
if ($path !== '/' && is_file($asFile) && !str_ends_with($asFile, '.php')) {
    return false;
}

// public/spec/ has no directory-index behaviour of its own under php -S or
// Valet (both route everything through here) — the static-file check above
// only matches an exact filename, not a bare directory request.
if ($path === '/spec' || $path === '/spec/') {
    header('Location: /spec/index.html', true, 302);
    exit;
}

if (!isset($routes[$path])) {
    renderErrorPage(404, t('error.404'));
}

require $routes[$path];
