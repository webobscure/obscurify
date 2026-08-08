<?php

namespace App\Domain\Catalog\Http\Controllers;

use App\Domain\Catalog\Application\CreateProductOption;
use App\Domain\Catalog\Application\DeleteProductOption;
use App\Domain\Catalog\Application\UpdateProductOption;
use App\Domain\Catalog\Http\Requests\StoreProductOptionRequest;
use App\Domain\Catalog\Http\Requests\UpdateProductOptionRequest;
use App\Domain\Catalog\Http\Resources\ProductOptionResource;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductOptionController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        $options = $product->options()->with('values')->get();

        return ProductOptionResource::collection($options);
    }

    public function store(StoreProductOptionRequest $request, Product $product, CreateProductOption $action): ProductOptionResource
    {
        $option = $action->handle($product, $request->validated());

        return new ProductOptionResource($option);
    }

    public function update(UpdateProductOptionRequest $request, Product $product, ProductOption $option, UpdateProductOption $action): ProductOptionResource
    {
        $this->assertBelongsToProduct($product, $option);

        $option = $action->handle($option, $request->validated());

        return new ProductOptionResource($option);
    }

    public function destroy(Product $product, ProductOption $option, DeleteProductOption $action): Response
    {
        $this->assertBelongsToProduct($product, $option);

        $action->handle($option);

        return response()->noContent();
    }

    /**
     * Both $product and $option are already tenant-scoped by their own
     * route model binding — this only guards against Option B being
     * addressed through a different Product A within the same store.
     */
    private function assertBelongsToProduct(Product $product, ProductOption $option): void
    {
        if ($option->product_id !== $product->id) {
            throw new NotFoundHttpException;
        }
    }
}
