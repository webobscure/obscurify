<?php

namespace App\Domain\Search\Application;

use App\Domain\Search\Enums\SearchAnalyticsEventType;
use App\Domain\Search\Models\SearchAnalyticsEvent;
use App\Domain\Search\Models\SearchQuery;
use App\Shared\Commerce\Application\RecordOutboxEvent;

/**
 * Spec section 12: "Clicked product", feeding "Search CTR". Also
 * records a real `SearchResultClicked` Platform Event so Analytics'
 * own `search_click_count` metric (see AnalyticsProjector/
 * MetricCalculator) reflects it the same real-time way every other
 * Analytics metric does.
 */
final class RecordSearchClick
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(?SearchQuery $searchQuery, string $productId, ?int $position): SearchAnalyticsEvent
    {
        $event = SearchAnalyticsEvent::query()->create([
            'search_query_id' => $searchQuery?->id,
            'event_type' => SearchAnalyticsEventType::ResultClicked->value,
            'product_id' => $productId,
            'position' => $position,
            'occurred_at' => now(),
        ]);

        $this->recordOutboxEvent->handle('SearchResultClicked', 'Product', $productId, ['product_id' => $productId, 'search_query_id' => $searchQuery?->id]);

        return $event;
    }
}
