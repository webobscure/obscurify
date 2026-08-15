<?php

namespace App\Domain\Search\Support;

use App\Domain\Search\Models\SearchQuery;

final readonly class SearchResult
{
    /**
     * @param  list<SearchResultItem>  $items
     * @param  array<string, mixed>  $facets
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public array $facets,
        public SearchQuery $searchQuery,
    ) {}
}
