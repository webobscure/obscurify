<?php

namespace App\Domain\Catalog\Http\Controllers;

use App\Domain\Catalog\Application\CreateProductOptionValue;
use App\Domain\Catalog\Application\DeleteProductOptionValue;
use App\Domain\Catalog\Application\UpdateProductOptionValue;
use App\Domain\Catalog\Http\Requests\StoreProductOptionValueRequest;
use App\Domain\Catalog\Http\Requests\UpdateProductOptionValueRequest;
use App\Domain\Catalog\Http\Resources\ProductOptionValueResource;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductOptionValue;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductOptionValueController extends Controller
{
    public function store(StoreProductOptionValueRequest $request, Product $product, ProductOption $option, CreateProductOptionValue $action): ProductOptionValueResource
    {
        $this->assertBelongsToProduct($product, $option);

        $value = $action->handle($option, $request->validated());

        return new ProductOptionValueResource($value);
    }

    public function update(
        UpdateProductOptionValueRequest $request,
        Product $product,
        ProductOption $option,
        ProductOptionValue $value,
        UpdateProductOptionValue $action,
    ): ProductOptionValueResource {
        $this->assertBelongsToProduct($product, $option);
        $this->assertBelongsToOption($option, $value);

        $value = $action->handle($value, $request->validated());

        return new ProductOptionValueResource($value);
    }

    public function destroy(
        Product $product,
        ProductOption $option,
        ProductOptionValue $value,
        DeleteProductOptionValue $action,
    ): Response {
        $this->assertBelongsToProduct($product, $option);
        $this->assertBelongsToOption($option, $value);

        $action->handle($value);

        return response()->noContent();
    }

    private function assertBelongsToProduct(Product $product, ProductOption $option): void
    {
        if ($option->product_id !== $product->id) {
            throw new NotFoundHttpException;
        }
    }

    private function assertBelongsToOption(ProductOption $option, ProductOptionValue $value): void
    {
        if ($value->product_option_id !== $option->id) {
            throw new NotFoundHttpException;
        }
    }
}
