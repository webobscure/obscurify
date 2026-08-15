<?php

namespace App\Domain\Search\Support\Providers;

use App\Domain\Search\Models\SearchDocument;
use App\Domain\Search\Models\SearchProvider;
use App\Domain\Search\Support\SearchFilters;
use App\Domain\Search\Support\SearchProviderContract;
use App\Domain\Search\Support\SearchProviderMatch;
use App\Domain\Search\Support\SearchProviderQuery;
use App\Domain\Search\Support\SearchProviderResult;
use App\Domain\Search\Support\SearchProviderSuggestion;
use App\Domain\Search\Support\SearchTextNormalizer;
use App\Domain\Search\Support\SearchTypoTolerance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The default reference implementation (spec: "The default
 * implementation must be DatabaseSearchProvider") — queries
 * SearchDocument directly with SQL, no external engine. `index()`/
 * `bulkIndex()`/`delete()` are no-ops here specifically: for this
 * provider, the SearchDocument row itself *is* the index (written by
 * BuildSearchDocument/RemoveSearchDocument before this contract is ever
 * called) — a real remote provider (Meilisearch etc.) would instead
 * push the document to its own engine in these methods. See
 * docs/architecture/search.md §2.
 *
 * Relevance is a hand-scored heuristic (exact title match beats prefix
 * beats contains beats a description/tag/vendor hit), not a real
 * text-ranking algorithm — the honest tradeoff of a DB-backed default;
 * see ADR-028.
 */
final class DatabaseSearchProvider implements SearchProviderContract
{
    public const int EXACT_TITLE_SCORE = 100;

    public const int PREFIX_TITLE_SCORE = 50;

    public const int CONTAINS_TITLE_SCORE = 25;

    public const int SECONDARY_FIELD_SCORE = 10;

    public function __construct(
        private readonly SearchTextNormalizer $normalizer,
        private readonly SearchTypoTolerance $typoTolerance,
    ) {}

    public function code(): string
    {
        return SearchProvider::DATABASE;
    }

    public function index(SearchDocument $document): void
    {
        // No-op — see class docblock.
    }

    public function bulkIndex(iterable $documents): void
    {
        // No-op — see class docblock.
    }

    public function delete(string $storeId, string $productId): void
    {
        // No-op — see class docblock.
    }

    public function search(SearchProviderQuery $query): SearchProviderResult
    {
        $base = $this->baseQuery($query->storeId, $query->filters);

        $tokens = $query->queryTokens;
        $filtered = (clone $base);
        $this->applyTextMatch($filtered, $tokens);
        $total = (clone $filtered)->count();

        if ($total === 0 && $tokens !== []) {
            $corrected = $this->correctTokens($query->storeId, $tokens);

            if ($corrected !== null && $corrected !== $tokens) {
                $tokens = $corrected;
                $filtered = (clone $base);
                $this->applyTextMatch($filtered, $tokens);
                $total = (clone $filtered)->count();
            }
        }

        $candidates = (clone $filtered)
            ->orderByDesc('sales_count')
            ->orderByDesc('product_created_at')
            ->limit($query->limit)
            ->get();

        $matches = $candidates
            ->map(fn (SearchDocument $document) => new SearchProviderMatch($document->product_id, $this->relevanceScore($document, $tokens)))
            ->sortByDesc(fn (SearchProviderMatch $match) => $match->score)
            ->values()
            ->all();

        return new SearchProviderResult($matches, $total, $this->buildFacets($filtered));
    }

    /**
     * @return list<SearchProviderSuggestion>
     */
    public function suggestProducts(string $storeId, string $prefix, int $limit): array
    {
        $normalizedPrefix = $this->normalizer->normalize($prefix);

        if ($normalizedPrefix === '') {
            return [];
        }

        return SearchDocument::query()->withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('is_searchable', true)
            ->where('search_text', 'ilike', "%{$normalizedPrefix}%")
            ->orderByDesc('sales_count')
            ->limit($limit)
            ->get()
            ->map(fn (SearchDocument $document) => new SearchProviderSuggestion($document->product_id, $document->title, $document->thumbnail_url))
            ->all();
    }

    /**
     * @return Builder<SearchDocument>
     */
    private function baseQuery(string $storeId, SearchFilters $filters): Builder
    {
        $query = SearchDocument::query()->withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('is_searchable', true);

        if ($filters->categoryIds !== []) {
            $query->where(fn ($q) => $this->jsonbArrayOverlaps($q, 'category_ids', $filters->categoryIds));
        }

        if ($filters->collectionIds !== []) {
            $query->where(fn ($q) => $this->jsonbArrayOverlaps($q, 'collection_ids', $filters->collectionIds));
        }

        if ($filters->vendors !== []) {
            $query->whereIn('vendor', $filters->vendors);
        }

        if ($filters->productTypes !== []) {
            $query->whereIn('product_type', $filters->productTypes);
        }

        if ($filters->tags !== []) {
            $query->where(fn ($q) => $this->jsonbArrayOverlaps($q, 'tags', $filters->tags));
        }

        foreach ($filters->variantOptions as $option => $values) {
            if ($values === []) {
                continue;
            }

            $query->whereRaw(
                'exists (select 1 from jsonb_array_elements(variant_option_values) as elem where elem->>\'option\' = ? and elem->>\'value\' in ('.implode(',', array_fill(0, count($values), '?')).'))',
                [$option, ...$values],
            );
        }

        if ($filters->priceMin !== null) {
            $query->where('price_max', '>=', $filters->priceMin);
        }

        if ($filters->priceMax !== null) {
            $query->where('price_min', '<=', $filters->priceMax);
        }

        if ($filters->availability !== null) {
            $query->where('availability', $filters->availability);
        }

        return $query;
    }

    /**
     * @param  Builder<SearchDocument>  $query
     * @param  list<string>  $values
     */
    private function jsonbArrayOverlaps(Builder $query, string $column, array $values): Builder
    {
        // Deliberately not Postgres jsonb's own "?|" array-overlap
        // operator: its literal "?" character is indistinguishable from
        // a PDO bind placeholder to Laravel's query builder, which
        // corrupts every subsequent binding in the same query (a real
        // bug caught by this milestone's own test suite — see
        // docs/adr/028-search-platform.md). EXISTS+ANY() is the
        // PDO-safe equivalent: true when the column's array shares at
        // least one element with the given text array.
        return $query->whereRaw(
            "exists (select 1 from jsonb_array_elements_text({$column}) as elem where elem = any(?::text[]))",
            ['{'.implode(',', array_map(fn ($v) => '"'.str_replace('"', '\\"', $v).'"', $values)).'}'],
        );
    }

    /**
     * @param  Builder<SearchDocument>  $query
     * @param  list<list<string>>  $tokenGroups
     */
    private function applyTextMatch(Builder $query, array $tokenGroups): void
    {
        // Each group is one query word plus its synonym alternatives —
        // any one alternative matching satisfies that word (OR within
        // the group), while every group must find some match (AND
        // across groups). A flat AND over every alternative would
        // require e.g. both "tv" and "television" to co-occur, which a
        // document naturally never does.
        foreach ($tokenGroups as $group) {
            $query->where(function ($q) use ($group) {
                foreach ($group as $token) {
                    $q->orWhere('search_text', 'ilike', "%{$token}%");
                }
            });
        }
    }

    /**
     * Typo-corrects only the primary (first) alternative of each group,
     * replacing that group with a single-element corrected group — an
     * acceptable simplification that deliberately doesn't re-run
     * synonym expansion on the corrected word (see ADR-028).
     *
     * @param  list<list<string>>  $tokenGroups
     * @return list<list<string>>|null
     */
    private function correctTokens(string $storeId, array $tokenGroups): ?array
    {
        $dictionary = $this->distinctTitleWords($storeId);

        if ($dictionary === []) {
            return null;
        }

        $corrected = [];
        $changed = false;

        foreach ($tokenGroups as $group) {
            $primary = $group[0] ?? '';
            $suggestion = $this->typoTolerance->correct($primary, $dictionary);

            if ($suggestion !== null && $suggestion !== $primary) {
                $changed = true;
                $corrected[] = [$suggestion];
            } else {
                $corrected[] = $group;
            }
        }

        return $changed ? $corrected : null;
    }

    /**
     * A bounded candidate dictionary for typo correction — every
     * distinct word across this store's indexed titles, capped well
     * short of a full-catalog scan.
     *
     * @return list<string>
     */
    private function distinctTitleWords(string $storeId): array
    {
        $titles = SearchDocument::query()->withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('is_searchable', true)
            ->limit(1000)
            ->pluck('title');

        $words = [];

        foreach ($titles as $title) {
            foreach ($this->normalizer->tokenize($title) as $word) {
                $words[$word] = true;
            }
        }

        return array_keys($words);
    }

    /**
     * @param  list<list<string>>  $tokenGroups
     */
    private function relevanceScore(SearchDocument $document, array $tokenGroups): float
    {
        if ($tokenGroups === []) {
            return (float) $document->sales_count + (float) $document->popularity;
        }

        $normalizedTitle = $this->normalizer->normalize($document->title);
        $score = 0.0;

        foreach ($tokenGroups as $group) {
            // Score each group by its single best-matching alternative —
            // e.g. "tv" scores as a contains-match while "television"
            // (the synonym) scores as the exact title match; the group
            // as a whole contributes only the strongest of the two.
            $bestGroupScore = 0;

            foreach ($group as $token) {
                if ($normalizedTitle === $token) {
                    $bestGroupScore = max($bestGroupScore, self::EXACT_TITLE_SCORE);
                } elseif (str_starts_with($normalizedTitle, $token)) {
                    $bestGroupScore = max($bestGroupScore, self::PREFIX_TITLE_SCORE);
                } elseif (str_contains($normalizedTitle, $token)) {
                    $bestGroupScore = max($bestGroupScore, self::CONTAINS_TITLE_SCORE);
                } elseif (str_contains($document->search_text, $token)) {
                    $bestGroupScore = max($bestGroupScore, self::SECONDARY_FIELD_SCORE);
                }
            }

            $score += $bestGroupScore;
        }

        return $score + $document->search_score;
    }

    /**
     * @param  Builder<SearchDocument>  $filtered
     * @return array<string, array<string, int>|array{min: int|null, max: int|null}>
     */
    private function buildFacets(Builder $filtered): array
    {
        $sql = $filtered->toBase();

        $priceRange = (clone $sql)->selectRaw('min(price_min) as min, max(price_max) as max')->first();

        return [
            'vendor' => $this->countFacet($sql, 'vendor'),
            'product_type' => $this->countFacet($sql, 'product_type'),
            'availability' => $this->countBooleanFacet($sql, 'availability'),
            'category' => $this->countJsonbArrayFacet($sql, 'category_ids'),
            'collection' => $this->countJsonbArrayFacet($sql, 'collection_ids'),
            'tags' => $this->countJsonbArrayFacet($sql, 'tags'),
            'variant_options' => $this->countVariantOptionFacet($sql),
            'price' => ['min' => $priceRange?->min, 'max' => $priceRange?->max],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function countFacet(\Illuminate\Database\Query\Builder $sql, string $column): array
    {
        return (clone $sql)
            ->whereNotNull($column)
            ->selectRaw("{$column} as facet_value, count(*) as facet_count")
            ->groupBy($column)
            ->pluck('facet_count', 'facet_value')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function countBooleanFacet(\Illuminate\Database\Query\Builder $sql, string $column): array
    {
        $rows = (clone $sql)
            ->selectRaw("{$column} as facet_value, count(*) as facet_count")
            ->groupBy($column)
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $key = $row->facet_value ? 'true' : 'false';
            $result[$key] = (int) $row->facet_count;
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function countJsonbArrayFacet(\Illuminate\Database\Query\Builder $sql, string $column): array
    {
        $rows = DB::query()
            ->fromSub($sql, 'filtered')
            ->crossJoin(DB::raw("jsonb_array_elements_text(filtered.{$column}) as facet_value"))
            ->selectRaw('facet_value, count(*) as facet_count')
            ->groupBy('facet_value')
            ->get();

        return $rows->pluck('facet_count', 'facet_value')->map(fn ($count) => (int) $count)->all();
    }

    /**
     * @return array<string, int>
     */
    private function countVariantOptionFacet(\Illuminate\Database\Query\Builder $sql): array
    {
        $rows = DB::query()
            ->fromSub($sql, 'filtered')
            ->crossJoin(DB::raw('jsonb_array_elements(filtered.variant_option_values) as elem'))
            ->selectRaw("(elem->>'option') || ':' || (elem->>'value') as facet_value, count(*) as facet_count")
            ->groupBy('facet_value')
            ->get();

        return $rows->pluck('facet_count', 'facet_value')->map(fn ($count) => (int) $count)->all();
    }
}
