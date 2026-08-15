<?php

namespace App\Domain\Search\Support;

use App\Domain\Search\Enums\SearchSortOption;

/**
 * Everything a SearchProviderContract::search() call needs — query
 * tokens are already normalized and synonym-expanded by the time this
 * reaches a provider (see SearchTextNormalizer/SynonymExpander), so
 * every provider implementation gets identical input regardless of
 * which one is active.
 *
 * queryTokens is a list of *groups* — one group per original query
 * word, each group holding that word plus its synonym alternatives.
 * Within a group the alternatives are OR-matched; groups themselves
 * are AND-required against each other. A word with no synonyms is
 * simply a single-element group.
 */
final readonly class SearchProviderQuery
{
    /**
     * @param  list<list<string>>  $queryTokens
     */
    public function __construct(
        public string $storeId,
        public array $queryTokens,
        public SearchFilters $filters,
        public SearchSortOption $sort,
        public int $limit,
    ) {}
}
