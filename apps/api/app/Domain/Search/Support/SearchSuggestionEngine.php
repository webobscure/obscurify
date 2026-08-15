<?php

namespace App\Domain\Search\Support;

use App\Domain\Catalog\Models\Category;
use App\Domain\Collections\Models\Collection;
use App\Domain\Search\Enums\SearchSuggestionType;
use App\Domain\Search\Models\SearchProvider;
use App\Domain\Search\Models\SearchSettings;
use App\Domain\Search\Models\SearchSuggestion;
use App\Domain\Stores\Models\Store;

/**
 * Autocomplete (spec section 8) — products come from the active
 * SearchProviderContract (never a direct Product query); collections/
 * categories are simple prefix lookups against their own small tables
 * (a handful to a few hundred rows per store, not a search-index
 * concern); popular queries are read from the materialized
 * SearchSuggestion cache (see RefreshSearchSuggestionsCommand), never
 * aggregated live.
 */
final class SearchSuggestionEngine
{
    public function __construct(
        private readonly SearchProviderRegistry $registry,
        private readonly SearchTextNormalizer $normalizer,
    ) {}

    /**
     * @return array{products: list<SearchProviderSuggestion>, collections: list<array{id: string, title: string}>, categories: list<array{id: string, title: string}>, popular_queries: list<string>}
     */
    public function suggest(Store $store, string $prefix): array
    {
        $settings = SearchSettings::query()->where('store_id', $store->id)->first();
        $limit = $settings !== null ? $settings->autocomplete_limit : 8;
        $providerCode = $settings?->activeProviderCode() ?? SearchProvider::DATABASE;

        $normalizedPrefix = $this->normalizer->normalize($prefix);

        return [
            'products' => $prefix === '' ? [] : $this->registry->resolve($providerCode)->suggestProducts($store->id, $prefix, $limit),
            'collections' => $this->prefixMatch(Collection::class, $store->id, $normalizedPrefix, $limit),
            'categories' => $this->prefixMatch(Category::class, $store->id, $normalizedPrefix, $limit),
            'popular_queries' => $this->popularQueries($store->id, $normalizedPrefix, $limit),
        ];
    }

    /**
     * @param  class-string<Category|Collection>  $modelClass
     * @return list<array{id: string, title: string}>
     */
    private function prefixMatch(string $modelClass, string $storeId, string $normalizedPrefix, int $limit): array
    {
        if ($normalizedPrefix === '') {
            return [];
        }

        return $modelClass::query()->withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('title', 'ilike', "{$normalizedPrefix}%")
            ->limit($limit)
            ->get(['id', 'title'])
            ->map(fn ($model) => ['id' => $model->id, 'title' => $model->title])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function popularQueries(string $storeId, string $normalizedPrefix, int $limit): array
    {
        return SearchSuggestion::query()->withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('type', SearchSuggestionType::Query->value)
            ->where('is_active', true)
            ->when($normalizedPrefix !== '', fn ($q) => $q->where('term', 'ilike', "{$normalizedPrefix}%"))
            ->orderByDesc('score')
            ->limit($limit)
            ->pluck('term')
            ->all();
    }
}
