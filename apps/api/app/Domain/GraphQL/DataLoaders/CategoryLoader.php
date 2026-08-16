<?php

namespace App\Domain\GraphQL\DataLoaders;

use App\Domain\Catalog\Models\Category;
use App\Domain\GraphQL\Support\DataLoader;
use GraphQL\Deferred;
use Illuminate\Support\Facades\DB;

/**
 * Batches `Category` lookups by id, and separately batches "which
 * categories does this product belong to" by product id
 * (`Product.categories` — see CatalogTypes and CollectionLoader's own
 * docblock for why this is a genuine GraphQL-level N+1 risk distinct
 * from Eloquent eager loading).
 */
final class CategoryLoader
{
    private DataLoader $loader;

    private DataLoader $byProduct;

    public function __construct()
    {
        $this->loader = new DataLoader(function (array $ids) {
            return Category::query()
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id')
                ->all();
        });

        $this->byProduct = new DataLoader(function (array $productIds) {
            $model = new Category;

            $rows = DB::table('product_categories')
                ->join('categories', 'categories.id', '=', 'product_categories.category_id')
                ->whereIn('product_categories.product_id', $productIds)
                ->select('product_categories.product_id as __product_id', 'categories.*')
                ->get();

            return $rows->groupBy('__product_id')
                ->map(fn ($group) => $group->map(fn ($row) => $model->newFromBuilder((array) $row))->values()->all())
                ->all();
        });
    }

    public function load(string $id): Deferred
    {
        return $this->loader->load($id);
    }

    public function loadForProduct(string $productId): Deferred
    {
        return $this->byProduct->load($productId);
    }
}
