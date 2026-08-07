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

    /** Returns ['allowed' => bool, 'remaining' => int] — remaining is surfaced to debug mode. */
    public function allow(string $rawOrigin): array
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
     *
     * nginx's default `proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for`
     * APPENDS the real client address to whatever the request arrived with — it
     * doesn't overwrite. So the trusted hop is the LAST entry, not the first; the
     * first is whatever the visitor sent. Taking [0] would hand a visitor an
     * unlimited-cardinality rate-limit key just by setting their own header.
     *
     * ponytail: assumes exactly one proxy hop. If EasyEngine's Docker layer adds
     * a second hop, this needs the second-to-last entry instead — verify against
     * the real nginx/docker-compose config once the droplet is reachable.
     */
    public static function resolveClientOrigin(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($forwarded) && $forwarded !== '') {
            $hops = explode(',', $forwarded);
            return trim(end($hops));
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
