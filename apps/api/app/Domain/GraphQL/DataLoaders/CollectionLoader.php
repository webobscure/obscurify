<?php

namespace App\Domain\GraphQL\DataLoaders;

use App\Domain\Collections\Enums\CollectionStatus;
use App\Domain\Collections\Models\Collection;
use App\Domain\GraphQL\Support\DataLoader;
use GraphQL\Deferred;
use Illuminate\Support\Facades\DB;

/**
 * Batches `Collection` lookups by id, and separately batches
 * "which collections does this product belong to" by product id
 * (`Product.collections` — see CatalogTypes) — a genuine GraphQL-level
 * N+1 risk distinct from Eloquent's own eager loading: the top-level
 * `products`/`product` query resolver has no way to know in advance
 * whether a given request's selection set will ask for `collections`
 * on each product, so it can't pre-eager-load the relation the way a
 * fixed REST resource always would.
 */
final class CollectionLoader
{
    private DataLoader $loader;

    private DataLoader $byProduct;

    public function __construct()
    {
        $this->loader = new DataLoader(function (array $ids) {
            return Collection::query()
                ->where('status', CollectionStatus::Active)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id')
                ->all();
        });

        $this->byProduct = new DataLoader(function (array $productIds) {
            $model = new Collection;

            $rows = DB::table('collection_products')
                ->join('collections', 'collections.id', '=', 'collection_products.collection_id')
                ->whereIn('collection_products.product_id', $productIds)
                ->where('collections.status', CollectionStatus::Active->value)
                ->select('collection_products.product_id as __product_id', 'collections.*')
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
