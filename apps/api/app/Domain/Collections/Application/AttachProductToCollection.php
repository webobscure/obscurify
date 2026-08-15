<?php

namespace App\Domain\Collections\Application;

use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionProduct;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class AttachProductToCollection
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * Both $collection and $product are resolved through route model
     * binding under the same active TenantContext, so a cross-tenant pair
     * can never reach here — a Store B product id simply fails to resolve
     * while Store A is active. Idempotent: attaching an already-member
     * product is a no-op rather than a duplicate-key error.
     *
     * Fires `ProductUpdated`, not a new event type — a collection
     * membership change means exactly one product needs reindexing (its
     * own collection_ids facet), the same trigger a title/description
     * edit fires (see docs/architecture/search.md §4).
     */
    public function handle(Collection $collection, Product $product): CollectionProduct
    {
        $link = CollectionProduct::query()->firstOrCreate([
            'collection_id' => $collection->id,
            'product_id' => $product->id,
        ]);

        $this->recordOutboxEvent->handle('ProductUpdated', 'Product', $product->id, ['product_id' => $product->id]);

        return $link;
    }
}
