<?php

use Valet\Drivers\ValetDriver;

// Valet's default driver (BasicWithPublicValetDriver) tries `public/<uri>`
// as its own front controller first — so a request for /input.php would
// execute public/input.php directly, bypassing public/index.php and the
// config.php it loads. This driver forces index.php as the sole front
// controller (matching `php -S ... public/index.php` dev mode); only real
// static assets serve directly, never a .php file.
class LocalValetDriver extends ValetDriver
{
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return is_dir($sitePath . '/public');
    }

    public function isStaticFile(string $sitePath, string $siteName, string $uri)
    {
        $path = $sitePath . '/public' . $uri;

        if (is_file($path) && !str_ends_with($path, '.php')) {
            return $path;
        }

        return false;
    }

    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        return $sitePath . '/public/index.php';
    }
}
