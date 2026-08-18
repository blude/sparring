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
     * Behind EasyEngine's nginx→PHP-FPM, REMOTE_ADDR is set from the same-host
     * fastcgi hop, so it already carries the real visitor IP. X-Forwarded-For
     * is trusted too: verified 2026-08-19 against sparring-live's own droplet
     * (sparringmethod.com) with `curl -H "X-Forwarded-For: 1.2.3.4"` — nginx
     * here APPENDS the real connecting IP rather than overwriting (unlike the
     * sibling site checked 2026-08-07), so the response came back
     * `"1.2.3.4, 2.214.252.236"`. Taking the last comma-separated entry (not
     * the first) lands on the real IP either way — a spoofed value only ever
     * occupies an earlier position, never the last.
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
