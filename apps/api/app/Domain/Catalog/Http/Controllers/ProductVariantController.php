<?php

namespace App\Domain\Catalog\Http\Controllers;

use App\Domain\Catalog\Application\CreateProductVariant;
use App\Domain\Catalog\Application\DeleteProductVariant;
use App\Domain\Catalog\Application\UpdateProductVariant;
use App\Domain\Catalog\Http\Requests\StoreProductVariantRequest;
use App\Domain\Catalog\Http\Requests\UpdateProductVariantRequest;
use App\Domain\Catalog\Http\Resources\ProductVariantResource;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductVariantController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        $variants = $product->variants()->with('optionValues')->orderBy('created_at')->get();

        return ProductVariantResource::collection($variants);
    }

    public function store(StoreProductVariantRequest $request, Product $product, CreateProductVariant $action): ProductVariantResource
    {
        $variant = $action->handle($product, $request->validated());

        return new ProductVariantResource($variant->load('optionValues'));
    }

    public function update(
        UpdateProductVariantRequest $request,
        Product $product,
        ProductVariant $variant,
        UpdateProductVariant $action,
    ): ProductVariantResource {
        $this->assertBelongsToProduct($product, $variant);

        $variant = $action->handle($variant, $request->validated());

        return new ProductVariantResource($variant->load('optionValues'));
    }

    public function destroy(Product $product, ProductVariant $variant, DeleteProductVariant $action): Response
    {
        $this->assertBelongsToProduct($product, $variant);

        $action->handle($variant);

        return response()->noContent();
    }

    /**
     * Both $product and $variant are already tenant-scoped by their own
     * route model binding — this only guards against Variant B being
     * addressed through a different Product A within the same store.
     */
    private function assertBelongsToProduct(Product $product, ProductVariant $variant): void
    {
        if ($variant->product_id !== $product->id) {
            throw new NotFoundHttpException;
        }
    }
}
