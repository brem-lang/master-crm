<?php

namespace App\Support;

class SafeUrl
{
    /**
     * Guard against SSRF: only allow http/https URLs that don't resolve to a private,
     * reserved, or loopback address.
     */
    public static function isSafe(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || $host === null) {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            // gethostbyname() failed to resolve the host.
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
