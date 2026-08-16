<?php

namespace App\Domain\GraphQL\DataLoaders;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\GraphQL\Support\DataLoader;
use GraphQL\Deferred;

/**
 * Batches `ProductVariant` lookups by id (e.g. a cart's N line items,
 * each resolving its own `variant` field).
 */
final class VariantLoader
{
    private DataLoader $loader;

    public function __construct()
    {
        $this->loader = new DataLoader(function (array $ids) {
            return ProductVariant::query()
                ->whereIn('id', $ids)
                ->with(['product', 'optionValues.option', 'inventoryItem.levels'])
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
