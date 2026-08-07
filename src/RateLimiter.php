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
     * is trusted too: verified 2026-08-07 against a live EasyEngine site
     * (diasnormais.com) that nginx OVERWRITES this header with the real
     * connecting IP rather than appending to it — a spoofed
     * `X-Forwarded-For: 1.2.3.4` sent by curl came back as the real client
     * address, not the injected value. So the header is never visitor-supplied
     * from outside nginx's own hop.
     *
     * Parsing still takes the LAST comma-separated entry (not the first) as
     * defense in depth — correct for both an overwritten single value and the
     * append behavior (`$proxy_add_x_forwarded_for`) some nginx configs use
     * instead, without needing to know which one is live.
     *
     * ponytail: verified on a sibling EasyEngine site, not sparring-live's own
     * droplet — re-run the debug-headers.php spoof test there before opening
     * night in case that site's nginx config differs.
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
