<?php

namespace App\Domain\Search\Application;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Search\Jobs\IndexProductJob;
use App\Domain\Search\Jobs\RemoveProductFromIndexJob;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Models\OutboxEvent;

/**
 * The 5th ProcessOutboxEventsCommand subscriber (after
 * DispatchWebhooksForEvent M11, DispatchWorkflowTriggersForEvent M19,
 * AnalyticsProjector M20, DispatchNotificationsForEvent M21) — reuses
 * Platform Events for incremental indexing (spec section 4). Every
 * search-triggering event this milestone introduces carries
 * aggregate_type=Product/aggregate_id=the product's own id (see
 * PlatformEventCatalog's own docblock and ADR-028), so this subscriber
 * only needs to special-case `InventoryChanged` (M20's own event,
 * aggregate_type=InventoryItem) and the two catalog-metadata events
 * (`CollectionUpdated`/`CategoryUpdated`, which don't reindex any
 * product — facet labels are resolved live, not stored redundantly in
 * SearchDocument).
 */
final class SearchIndexingSubscriber
{
    private const array PRODUCT_REINDEX_EVENTS = [
        'ProductCreated',
        'ProductUpdated',
        'VariantUpdated',
        'PriceChanged',
        'VisibilityChanged',
    ];

    public function handle(OutboxEvent $event, Store $store): void
    {
        if (in_array($event->event_type, self::PRODUCT_REINDEX_EVENTS, true)) {
            IndexProductJob::dispatch($store->id, $event->aggregate_id);

            return;
        }

        if ($event->event_type === 'ProductDeleted') {
            RemoveProductFromIndexJob::dispatch($store->id, $event->aggregate_id);

            return;
        }

        if ($event->event_type === 'InventoryChanged') {
            $this->handleInventoryChanged($store, $event);
        }
    }

    private function handleInventoryChanged(Store $store, OutboxEvent $event): void
    {
        $item = InventoryItem::withoutGlobalScopes()->with('variant')->find($event->aggregate_id);
        $productId = $item?->variant?->product_id;

        if ($productId === null) {
            return;
        }

        IndexProductJob::dispatch($store->id, $productId);
    }
}
