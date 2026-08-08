<?php

namespace App\Domain\Catalog\Http\Controllers;

use App\Domain\Catalog\Application\CreateProduct;
use App\Domain\Catalog\Application\DeleteProduct;
use App\Domain\Catalog\Application\UpdateProduct;
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
     * scope — this can never return another store's products.
     */
    public function index(): AnonymousResourceCollection
    {
        $products = Product::query()->orderByDesc('created_at')->paginate();

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
        return new ProductResource($product->load(['options.values', 'variants.optionValues', 'media', 'collections']));
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
