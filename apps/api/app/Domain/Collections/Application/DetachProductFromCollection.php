<?php

namespace App\Domain\Collections\Application;

use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionProduct;
use App\Shared\Commerce\Application\RecordOutboxEvent;

final class DetachProductFromCollection
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    public function handle(Collection $collection, Product $product): void
    {
        CollectionProduct::query()
            ->where('collection_id', $collection->id)
            ->where('product_id', $product->id)
            ->delete();

        $this->recordOutboxEvent->handle('ProductUpdated', 'Product', $product->id, ['product_id' => $product->id]);
    }
}
