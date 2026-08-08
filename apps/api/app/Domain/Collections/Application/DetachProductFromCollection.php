<?php

namespace App\Domain\Collections\Application;

use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionProduct;

final class DetachProductFromCollection
{
    public function handle(Collection $collection, Product $product): void
    {
        CollectionProduct::query()
            ->where('collection_id', $collection->id)
            ->where('product_id', $product->id)
            ->delete();
    }
}
