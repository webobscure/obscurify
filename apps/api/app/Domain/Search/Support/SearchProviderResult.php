<?php

namespace App\Domain\Search\Support;

/**
 * A provider-neutral result — the whole platform (merchandising,
 * ranking, facet rendering) reads only this shape, never a
 * provider-specific response.
 */
final readonly class SearchProviderResult
{
    /**
     * @param  list<SearchProviderMatch>  $matches  bounded to the query's own `limit`, ranked by the provider's own base text relevance
     * @param  array<string, array<string, int>|array{min: int|null, max: int|null}>  $facets  keyed by facet name; every facet except "price" is a value=>count map, "price" is a {min,max} range
     */
    public function __construct(
        public array $matches,
        public int $total,
        public array $facets,
    ) {}
}
