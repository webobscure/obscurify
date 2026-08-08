<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Storefront\Http\Resources\StorefrontProductResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StorefrontProductController extends Controller
{
    /**
     * Tenant-scoped by Product's BelongsToTenant global scope (the active
     * tenant comes from the resolved storefront domain) — this can never
     * return another store's products. Only `active` products are ever
     * visible here; draft/archived/soft-deleted never leave the admin API.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $sort = $request->query('sort', 'newest');
        $collectionSlug = $request->query('collection');
        $categorySlug = $request->query('category');

        $products = Product::query()
            ->where('status', ProductStatus::Active)
            ->when(
                $collectionSlug,
                fn ($query) => $query->whereHas(
                    'collections',
                    fn ($q) => $q->where('slug', $collectionSlug),
                ),
            )
            ->when(
                $categorySlug,
                fn ($query) => $query->whereHas(
                    'categories',
                    fn ($q) => $q->where('slug', $categorySlug),
                ),
            )
            ->withMin(['variants as min_variant_price' => fn ($q) => $q->where('status', ProductStatus::Active)], 'price_amount')
            ->with($this->eagerLoads())
            ->when(
                $sort === 'price_asc',
                fn ($query) => $query->orderBy('min_variant_price'),
            )
            ->when(
                $sort === 'price_desc',
                fn ($query) => $query->orderByDesc('min_variant_price'),
            )
            ->when(
                ! in_array($sort, ['price_asc', 'price_desc'], true),
                fn ($query) => $query->orderByDesc('created_at'),
            )
            ->paginate();

        return StorefrontProductResource::collection($products);
    }

    /**
     * Slug is looked up only within the active tenant (Product's global
     * scope) and only among `active` products — a Store-A hostname can
     * never resolve a Store-B (or draft/archived) product by slug.
     */
    public function show(string $slug): StorefrontProductResource
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', ProductStatus::Active)
            ->withMin(['variants as min_variant_price' => fn ($q) => $q->where('status', ProductStatus::Active)], 'price_amount')
            ->with($this->eagerLoads())
            ->firstOrFail();

        return new StorefrontProductResource($product);
    }

    /**
     * @return array<int|string, \Closure|string>
     */
    private function eagerLoads(): array
    {
        return [
            'variants' => fn ($q) => $q->where('status', ProductStatus::Active),
            'variants.optionValues.option',
            'variants.inventoryItem.levels',
            'media',
        ];
    }
}
