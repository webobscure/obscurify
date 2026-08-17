<?php

namespace App\Domain\Catalog\Http\Controllers;

use App\Domain\Catalog\Application\CreateProduct;
use App\Domain\Catalog\Application\DeleteProduct;
use App\Domain\Catalog\Application\UpdateProduct;
use App\Domain\Catalog\Http\Requests\SearchProductsRequest;
use App\Domain\Catalog\Http\Requests\StoreProductRequest;
use App\Domain\Catalog\Http\Requests\UpdateProductRequest;
use App\Domain\Catalog\Http\Resources\ProductResource;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class ProductController extends Controller
{
    /**
     * Scoped to the active tenant by Product's BelongsToTenant global
     * scope — this can never return another store's products. Every
     * filter is optional and ANDed together, same convention as
     * AdminCustomerController::index() (docs/design/DESIGN_SYSTEM.md
     * Products redesign — the list previously had no search/filter/sort/
     * per_page at all, silently truncating past the first page).
     */
    public function index(SearchProductsRequest $request): AnonymousResourceCollection
    {
        $query = Product::query();

        if ($search = $request->validated('search')) {
            $query->where(fn ($q) => $q
                ->where('title', 'ilike', "%{$search}%")
                ->orWhereHas('variants', fn ($v) => $v->where('sku', 'ilike', "%{$search}%")));
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($vendor = $request->validated('vendor')) {
            $query->where('vendor', 'ilike', "%{$vendor}%");
        }

        if ($productType = $request->validated('product_type')) {
            $query->where('product_type', 'ilike', "%{$productType}%");
        }

        if ($collectionId = $request->validated('collection_id')) {
            $query->whereHas('collections', fn ($q) => $q->where('collections.id', $collectionId));
        }

        [$sortColumn, $sortDirection] = match ($request->validated('sort')) {
            '-created_at' => ['created_at', 'asc'],
            'updated_at' => ['updated_at', 'desc'],
            '-updated_at' => ['updated_at', 'asc'],
            'title' => ['title', 'asc'],
            '-title' => ['title', 'desc'],
            default => ['created_at', 'desc'],
        };

        $products = $query->with(['media', 'variants'])
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($request->validated('per_page') ?? 50);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, CreateProduct $action): ProductResource
    {
        $product = $action->handle($request->validated());

        return new ProductResource($product);
    }

    /**
     * $product is resolved via tenant-scoped route model binding: a
     * cross-tenant id yields a 404, never another store's product.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['options.values', 'variants.optionValues', 'variants.media', 'media', 'collections']));
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $action): ProductResource
    {
        $product = $action->handle($product, $request->validated());

        return new ProductResource($product);
    }

    public function destroy(Product $product, DeleteProduct $action): Response
    {
        $action->handle($product);

        return response()->noContent();
    }
}
