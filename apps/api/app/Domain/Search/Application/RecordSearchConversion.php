<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Enums\SearchAnalyticsEventType;
use App\Domain\Search\Models\SearchAnalyticsEvent;
use App\Domain\Search\Models\SearchQuery;

/**
 * Spec section 12: "Search conversion" — attributes an order to the
 * search that led to it. Deliberately explicit rather than
 * automatically inferred from session matching: the storefront calls
 * this only when it actually knows which search a completed order
 * followed (e.g. the customer's most recent search this session before
 * checkout) — no heuristic session/time-window guessing lives here.
 * See docs/architecture/search.md §9 for this scope boundary.
 */
final class RecordSearchConversion
{
    public function handle(SearchQuery $searchQuery, string $productId, string $orderId): SearchAnalyticsEvent
    {
        return SearchAnalyticsEvent::query()->create([
            'search_query_id' => $searchQuery->id,
            'event_type' => SearchAnalyticsEventType::Converted->value,
            'product_id' => $productId,
            'order_id' => $orderId,
            'occurred_at' => now(),
        ]);
    }
}
