<?php

namespace App\Domain\GraphQL\DataLoaders;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\GraphQL\Support\DataLoader;
use GraphQL\Deferred;

/**
 * Batches `Product` lookups by id — e.g. a collection's product list
 * resolved as N sibling `product` fields on N `CollectionProduct`-like
 * edges becomes one `WHERE id IN (...)` query instead of N.
 */
final class ProductLoader
{
    private DataLoader $loader;

    public function __construct()
    {
        $this->loader = new DataLoader(function (array $ids) {
            return Product::query()
                ->where('status', ProductStatus::Active)
                ->whereIn('id', $ids)
                ->with(['variants' => fn ($q) => $q->where('status', ProductStatus::Active), 'variants.optionValues.option', 'variants.inventoryItem.levels', 'media'])
                ->get()
                ->keyBy('id')
                ->all();
        });
    }

    public function load(string $id): Deferred
    {
        return $this->loader->load($id);
    }
}
