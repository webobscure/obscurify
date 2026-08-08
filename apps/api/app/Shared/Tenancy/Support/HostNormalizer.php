<?php

namespace App\Shared\Tenancy\Support;

/**
 * Normalizes a Host header value into the canonical form `domains.domain`
 * rows are stored as: lowercase, no port, no surrounding whitespace.
 *
 * `www` vs non-`www` are deliberately left distinct — merging them silently
 * would let one Domain row resolve traffic its `domain` value doesn't
 * literally match. Store owners register both explicitly if they want both
 * to work.
 */
final class HostNormalizer
{
    public static function normalize(string $host): string
    {
        $host = trim($host);
        $host = strtolower($host);

        // Symfony's Request::getHost() already strips the port, but this
        // is defensive for any caller passing a raw Host header value.
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return rtrim($host, '.');
    }
}
