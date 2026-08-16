<?php

namespace App\Domain\GraphQL\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Spec sections 14/16: "Caching." Deliberately narrow — DataLoader (see
 * its own docblock) already gives every request its own N+1-free
 * *within-request* cache; this is the one additional *cross-request*
 * cache in this milestone, applied only to `categories` (a whole-store
 * tree that's read on nearly every storefront page load and changes
 * rarely — a merchant editing category structure is a deliberate admin
 * action, not a per-request event). A short, unconditional TTL is the
 * whole invalidation story: no cache-tag/event-driven busting, so a
 * structural change is visible within `TTL_SECONDS` at worst — an
 * accepted, documented tradeoff (see docs/adr/029-graphql-platform.md)
 * rather than building a full invalidation pipeline for one field.
 */
final class QueryCache
{
    public const int TTL_SECONDS = 60;

    /**
     * @param  callable(): mixed  $resolver
     */
    public static function remember(string $storeId, string $key, callable $resolver): mixed
    {
        return Cache::remember("graphql:{$storeId}:{$key}", self::TTL_SECONDS, $resolver);
    }
}
