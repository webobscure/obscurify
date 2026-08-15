<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Enums\SearchSuggestionType;
use App\Domain\Search\Models\SearchQuery;
use App\Domain\Search\Models\SearchSuggestion;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates recent SearchQuery rows into the SearchSuggestion cache
 * (spec section 8: "Popular queries") — the materialization step
 * RefreshSearchSuggestionsCommand runs on a schedule, kept out of the
 * request path entirely (spec section 16: "No table scans on every
 * request").
 */
final class RefreshSearchSuggestions
{
    private const int LOOKBACK_DAYS = 30;

    private const int MAX_TERMS = 50;

    public function handle(Store $store): int
    {
        $popular = SearchQuery::query()
            ->where('store_id', $store->id)
            ->where('normalized_query', '!=', '')
            ->where('created_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->select('normalized_query')
            ->selectRaw('count(*) as hits')
            ->groupBy('normalized_query')
            ->orderByDesc('hits')
            ->limit(self::MAX_TERMS)
            ->toBase()
            ->get();

        DB::transaction(function () use ($store, $popular) {
            SearchSuggestion::query()
                ->where('store_id', $store->id)
                ->where('type', SearchSuggestionType::Query->value)
                ->delete();

            foreach ($popular as $row) {
                SearchSuggestion::query()->create([
                    'term' => $row->normalized_query,
                    'type' => SearchSuggestionType::Query->value,
                    'score' => (int) $row->hits,
                    'is_active' => true,
                ]);
            }
        });

        return $popular->count();
    }
}
