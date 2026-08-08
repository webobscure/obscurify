<?php

namespace App\Domain\Collections\Application;

use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionProduct;

final class AttachProductToCollection
{
    /**
     * Both $collection and $product are resolved through route model
     * binding under the same active TenantContext, so a cross-tenant pair
     * can never reach here — a Store B product id simply fails to resolve
     * while Store A is active. Idempotent: attaching an already-member
     * product is a no-op rather than a duplicate-key error.
     */
    public function handle(Collection $collection, Product $product): CollectionProduct
    {
        return CollectionProduct::query()->firstOrCreate([
            'collection_id' => $collection->id,
            'product_id' => $product->id,
        ]);
    }
}
