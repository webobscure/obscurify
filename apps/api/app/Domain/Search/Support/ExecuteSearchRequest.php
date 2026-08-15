<?php

namespace App\Domain\Search\Support;

use App\Domain\Search\Enums\SearchSortOption;

final readonly class ExecuteSearchRequest
{
    public function __construct(
        public string $queryText = '',
        public SearchFilters $filters = new SearchFilters,
        public SearchSortOption $sort = SearchSortOption::Relevance,
        public int $page = 1,
        public int $perPage = 24,
        public ?string $customerId = null,
        public ?string $sessionId = null,
    ) {}
}
