<?php
declare(strict_types=1);

/**
 * TF-03: request-rate guard, not business logic. Windows are keyed on a hashed
 * origin (E-03.1) so no raw address is stored (QR-10). Threshold/window are set
 * below the provider's own limits (SC-04, C-02) — see config.php.
 */
final class RateLimiter
{
    public function __construct(private Store $store)
    {
    }

    public function allow(string $rawOrigin): bool
    {
        $hash = hash('sha256', $rawOrigin);
        return $this->store->checkAndIncrementRateLimit($hash, RATE_LIMIT_WINDOW_SECONDS, RATE_LIMIT_MAX_REQUESTS);
    }

    /**
     * Behind EasyEngine's nginx→PHP-FPM containers, REMOTE_ADDR can be the
     * Docker gateway for every visitor unless real-IP forwarding is set up —
     * verify on the actual droplet. X-Forwarded-For is trusted here because the
     * host terminates TLS and proxies every request; it is not visitor-supplied
     * from outside that chain.
     */
    public static function resolveClientOrigin(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($forwarded) && $forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
