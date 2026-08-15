<?php

namespace App\Domain\Search\Support;

use App\Domain\Search\Enums\SearchAnalyticsEventType;
use App\Domain\Search\Models\SearchAnalyticsEvent;
use App\Domain\Search\Models\SearchQuery;
use Illuminate\Support\Carbon;

/**
 * Backs the admin Search Analytics page (spec section 12: "Most
 * popular searches", "Top failed searches", "Search CTR", "Search
 * conversion"). Reads SearchQuery/SearchAnalyticsEvent directly —
 * these are the analytics domain's own tables, the same "read your own
 * projections, not a live commerce table" discipline
 * AnalyticsAggregator follows for Milestone 20's own metrics (this
 * data is additionally projected into Analytics proper via
 * `search_count`/`zero_result_search_count`/`search_click_count`, for
 * the cross-domain dashboard view — see AnalyticsProjector).
 */
final class SearchAnalyticsSummary
{
    private const int TOP_LIMIT = 10;

    /**
     * @return array<string, mixed>
     */
    public function build(string $storeId, Carbon $from, Carbon $to): array
    {
        $totalSearches = SearchQuery::query()->where('store_id', $storeId)->whereBetween('created_at', [$from, $to])->count();
        $totalClicks = SearchAnalyticsEvent::query()->where('store_id', $storeId)
            ->where('event_type', SearchAnalyticsEventType::ResultClicked->value)
            ->whereBetween('occurred_at', [$from, $to])
            ->count();
        $totalConversions = SearchAnalyticsEvent::query()->where('store_id', $storeId)
            ->where('event_type', SearchAnalyticsEventType::Converted->value)
            ->whereBetween('occurred_at', [$from, $to])
            ->count();

        return [
            'total_searches' => $totalSearches,
            'total_clicks' => $totalClicks,
            'total_conversions' => $totalConversions,
            'click_through_rate' => $totalSearches > 0 ? round($totalClicks / $totalSearches, 4) : 0.0,
            'conversion_rate' => $totalSearches > 0 ? round($totalConversions / $totalSearches, 4) : 0.0,
            'popular_searches' => $this->topQueries($storeId, $from, $to, zeroResultOnly: false),
            'zero_result_searches' => $this->topQueries($storeId, $from, $to, zeroResultOnly: true),
        ];
    }

    /**
     * @return list<array{query: string, count: int}>
     */
    private function topQueries(string $storeId, Carbon $from, Carbon $to, bool $zeroResultOnly): array
    {
        $query = SearchQuery::query()
            ->where('store_id', $storeId)
            ->where('normalized_query', '!=', '')
            ->whereBetween('created_at', [$from, $to]);

        if ($zeroResultOnly) {
            $query->where('result_count', 0);
        }

        return $query
            ->select('normalized_query')
            ->selectRaw('count(*) as hits')
            ->groupBy('normalized_query')
            ->orderByDesc('hits')
            ->limit(self::TOP_LIMIT)
            ->toBase()
            ->get()
            ->map(fn ($row) => ['query' => $row->normalized_query, 'count' => (int) $row->hits])
            ->all();
    }
}
