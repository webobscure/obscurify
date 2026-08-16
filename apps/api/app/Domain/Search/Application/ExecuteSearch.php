<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Enums\SearchRuleAction;
use App\Domain\Search\Enums\SearchSortOption;
use App\Domain\Search\Models\PinnedSearchResult;
use App\Domain\Search\Models\SearchDocument;
use App\Domain\Search\Models\SearchProvider;
use App\Domain\Search\Models\SearchQuery as SearchQueryModel;
use App\Domain\Search\Models\SearchRule;
use App\Domain\Search\Models\SearchSettings;
use App\Domain\Search\Support\ExecuteSearchRequest;
use App\Domain\Search\Support\SearchFacetBuilder;
use App\Domain\Search\Support\SearchProviderMatch;
use App\Domain\Search\Support\SearchProviderQuery;
use App\Domain\Search\Support\SearchProviderRegistry;
use App\Domain\Search\Support\SearchRankingEngine;
use App\Domain\Search\Support\SearchResult;
use App\Domain\Search\Support\SearchResultItem;
use App\Domain\Search\Support\SearchTextNormalizer;
use App\Domain\Search\Support\SynonymExpander;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Localization\LocaleContext;
use Illuminate\Support\Collection;

/**
 * The one orchestrator every search request goes through — spec
 * section 2's "depend only on SearchProviderContract" boundary in
 * practice: this class calls the contract exactly once
 * (`$provider->search()`) and layers merchandising (spec section 10),
 * ranking (spec section 11), and facets (spec section 6) on top of a
 * provider-neutral result, in that order — "Rules execute after
 * provider search but before pagination" (spec section 10), verified
 * literally by this method's own step order below.
 */
final class ExecuteSearch
{
    /**
     * How many of the provider's own best-relevance candidates are
     * pulled back before merchandising/ranking/pagination run — bounds
     * work regardless of catalog size while staying generous enough
     * that a boost/pin rule has a real pool to reorder within. See
     * ADR-028 for why this isn't the full filtered result set.
     */
    private const int CANDIDATE_WINDOW = 500;

    public function __construct(
        private readonly SearchTextNormalizer $normalizer,
        private readonly SynonymExpander $synonymExpander,
        private readonly SearchProviderRegistry $registry,
        private readonly SearchRankingEngine $rankingEngine,
        private readonly SearchFacetBuilder $facetBuilder,
        private readonly RecordOutboxEvent $recordOutboxEvent,
        private readonly LocaleContext $localeContext,
    ) {}

    public function handle(Store $store, ExecuteSearchRequest $request): SearchResult
    {
        $settings = SearchSettings::query()->where('store_id', $store->id)->first();
        $providerCode = $settings?->activeProviderCode() ?? SearchProvider::DATABASE;
        $provider = $this->registry->resolve($providerCode);

        $normalizedQuery = $this->normalizer->normalize($request->queryText);
        $rawTokens = $this->normalizer->tokenize($request->queryText);

        // list<list<string>> — one OR-group per query word (see
        // SynonymExpander/SearchProviderQuery); with synonyms disabled
        // each word is simply its own single-element group.
        $tokenGroups = ($settings === null || $settings->synonyms_enabled)
            ? $this->synonymExpander->expand($rawTokens, $this->localeContext->current())
            : array_map(fn (string $token) => [$token], $rawTokens);

        $providerResult = $provider->search(new SearchProviderQuery(
            storeId: $store->id,
            queryTokens: $tokenGroups,
            filters: $request->filters,
            sort: $request->sort,
            limit: self::CANDIDATE_WINDOW,
        ));

        [$matches, $hiddenInWindow] = $this->applyMerchandisingRules($normalizedQuery, $providerResult->matches);
        $pinned = $this->pinnedResults($normalizedQuery);
        $pinnedProductIds = $pinned->pluck('product_id')->all();

        // A pinned product always wins its keyword's top slot(s) — never
        // let the same product also occupy a ranked position.
        $matches = array_values(array_filter($matches, fn (SearchProviderMatch $m) => ! in_array($m->productId, $pinnedProductIds, true)));

        $candidateProductIds = [...$pinnedProductIds, ...array_map(fn (SearchProviderMatch $m) => $m->productId, $matches)];
        $documents = SearchDocument::query()->withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->whereIn('product_id', $candidateProductIds)
            ->get()
            ->keyBy('product_id');

        $rankedItems = $this->rankAndSort($matches, $documents, $request->sort);
        $pinnedItems = $this->pinnedItems($pinned, $documents);

        $allItems = [...$pinnedItems, ...$rankedItems];
        $total = max(0, $providerResult->total - $hiddenInWindow);

        $offset = ($request->page - 1) * $request->perPage;
        $page = array_slice($allItems, $offset, $request->perPage);

        $searchQuery = SearchQueryModel::query()->create([
            'query_text' => $request->queryText,
            'normalized_query' => $normalizedQuery,
            'filters' => $request->filters->toArray(),
            'sort' => $request->sort->value,
            'result_count' => $total,
            'customer_id' => $request->customerId,
            'session_id' => $request->sessionId,
        ]);

        $facets = ($settings === null || $settings->facets_enabled)
            ? $this->facetBuilder->build($store->id, $providerResult->facets)
            : [];

        $this->recordOutboxEvent->handle('SearchPerformed', 'SearchQuery', $searchQuery->id, [
            'search_query_id' => $searchQuery->id,
            'query_text' => $request->queryText,
            'result_count' => $total,
        ]);

        return new SearchResult(
            items: $page,
            total: $total,
            page: $request->page,
            perPage: $request->perPage,
            facets: $facets,
            searchQuery: $searchQuery,
        );
    }

    /**
     * @param  list<SearchProviderMatch>  $matches
     * @return array{0: list<SearchProviderMatch>, 1: int}
     */
    private function applyMerchandisingRules(string $normalizedQuery, array $matches): array
    {
        $rules = SearchRule::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('keyword')->orWhere('keyword', $normalizedQuery))
            ->get();

        $hideProductIds = $rules->where('action', SearchRuleAction::Hide)->pluck('product_id')->all();
        $boosts = $rules->where('action', SearchRuleAction::Boost)
            ->groupBy('product_id')
            ->map(fn ($group) => (int) $group->sum('boost_amount'));

        $hiddenInWindow = 0;
        $result = [];

        foreach ($matches as $match) {
            if (in_array($match->productId, $hideProductIds, true)) {
                $hiddenInWindow++;

                continue;
            }

            $boost = $boosts->get($match->productId, 0);
            $result[] = $boost !== 0 ? new SearchProviderMatch($match->productId, $match->score + $boost) : $match;
        }

        return [$result, $hiddenInWindow];
    }

    /**
     * @return Collection<int, PinnedSearchResult>
     */
    private function pinnedResults(string $normalizedQuery): Collection
    {
        return PinnedSearchResult::query()
            ->where('is_active', true)
            ->where('keyword', $normalizedQuery)
            ->orderBy('position')
            ->get();
    }

    /**
     * @param  list<SearchProviderMatch>  $matches
     * @param  Collection<string, SearchDocument>  $documents
     * @return list<SearchResultItem>
     */
    private function rankAndSort(array $matches, Collection $documents, SearchSortOption $sort): array
    {
        $scored = [];

        foreach ($matches as $match) {
            $document = $documents->get($match->productId);

            if ($document === null) {
                continue;
            }

            $finalScore = $this->rankingEngine->score($match->score, $document);
            $scored[] = $this->toItem($document, $finalScore, isPinned: false);
        }

        usort($scored, fn (SearchResultItem $a, SearchResultItem $b) => $this->compareForSort($a, $b, $documents, $sort));

        return $scored;
    }

    /**
     * @param  Collection<string, SearchDocument>  $documents
     */
    private function compareForSort(SearchResultItem $a, SearchResultItem $b, Collection $documents, SearchSortOption $sort): int
    {
        $docA = $documents->get($a->productId);
        $docB = $documents->get($b->productId);

        return match ($sort) {
            SearchSortOption::Newest => $docB?->product_created_at <=> $docA?->product_created_at,
            SearchSortOption::Oldest => $docA?->product_created_at <=> $docB?->product_created_at,
            SearchSortOption::PriceAsc => ($a->priceMin ?? PHP_INT_MAX) <=> ($b->priceMin ?? PHP_INT_MAX),
            SearchSortOption::PriceDesc => ($b->priceMax ?? 0) <=> ($a->priceMax ?? 0),
            SearchSortOption::BestSelling => ($docB !== null ? $docB->sales_count : 0) <=> ($docA !== null ? $docA->sales_count : 0),
            // MostViewed has no real signal yet (spec: "future-ready") —
            // popularity (currently mirroring sales_count) is the closest
            // stand-in until page-view tracking exists.
            SearchSortOption::MostViewed => ($docB !== null ? $docB->popularity : 0) <=> ($docA !== null ? $docA->popularity : 0),
            SearchSortOption::Manual => ($docB !== null ? $docB->search_score : 0) <=> ($docA !== null ? $docA->search_score : 0),
            default => $b->score <=> $a->score,
        };
    }

    /**
     * @param  Collection<int, PinnedSearchResult>  $pinned
     * @param  Collection<string, SearchDocument>  $documents
     * @return list<SearchResultItem>
     */
    private function pinnedItems(Collection $pinned, Collection $documents): array
    {
        $items = [];

        foreach ($pinned as $pin) {
            $document = $documents->get($pin->product_id);

            if ($document === null) {
                continue;
            }

            // The score value is informational only for a pinned item —
            // position within the pinned block already comes from the
            // PinnedSearchResult's own `position`, which the caller
            // iterates in order; PHP_FLOAT_MAX just signals "wins any
            // comparison" if this item were ever mixed into a sort.
            $items[] = $this->toItem($document, PHP_FLOAT_MAX, isPinned: true);
        }

        return $items;
    }

    private function toItem(SearchDocument $document, float $score, bool $isPinned): SearchResultItem
    {
        return new SearchResultItem(
            productId: $document->product_id,
            title: $document->title,
            slug: $document->slug,
            description: $document->description,
            vendor: $document->vendor,
            productType: $document->product_type,
            priceMin: $document->price_min,
            priceMax: $document->price_max,
            currency: $document->currency,
            thumbnailUrl: $document->thumbnail_url,
            availability: $document->availability,
            score: $score,
            isPinned: $isPinned,
        );
    }
}
