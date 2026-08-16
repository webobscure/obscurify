<?php

namespace App\Domain\RussianCommerce\Http\Controllers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\RussianCommerce\Application\CreateOrUpdateProductFiscalProfile;
use App\Domain\RussianCommerce\Application\DeleteProductFiscalProfile;
use App\Domain\RussianCommerce\Enums\FiscalizableType;
use App\Domain\RussianCommerce\Http\Requests\UpdateProductFiscalProfileRequest;
use App\Domain\RussianCommerce\Http\Resources\ProductFiscalProfileResource;
use App\Domain\RussianCommerce\Models\ProductFiscalProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Spec section 12 — one optional ProductFiscalProfile per Product or
 * ProductVariant (see ResolveProductFiscalProfile for the resolution
 * order this feeds). Nested under both `products/{product}/fiscal-
 * profile` and `products/{product}/variants/{variant}/fiscal-profile`
 * (see routes/api.php) — the two route groups share this controller
 * since CreateOrUpdateProductFiscalProfile/DeleteProductFiscalProfile
 * are already polymorphic over Product|ProductVariant.
 */
final class ProductFiscalProfileController extends Controller
{
    public function showForProduct(Product $product): JsonResponse|ProductFiscalProfileResource
    {
        return $this->show(FiscalizableType::Product, $product->id);
    }

    public function updateForProduct(UpdateProductFiscalProfileRequest $request, Product $product, CreateOrUpdateProductFiscalProfile $action): ProductFiscalProfileResource
    {
        return new ProductFiscalProfileResource($action->handle($product, $request->validated()));
    }

    public function destroyForProduct(Product $product, DeleteProductFiscalProfile $action): Response
    {
        $profile = ProductFiscalProfile::query()
            ->where('fiscalizable_type', FiscalizableType::Product->value)
            ->where('fiscalizable_id', $product->id)
            ->first();

        if ($profile !== null) {
            $action->handle($profile);
        }

        return response()->noContent();
    }

    public function showForVariant(Product $product, ProductVariant $variant): JsonResponse|ProductFiscalProfileResource
    {
        return $this->show(FiscalizableType::ProductVariant, $variant->id);
    }

    public function updateForVariant(UpdateProductFiscalProfileRequest $request, Product $product, ProductVariant $variant, CreateOrUpdateProductFiscalProfile $action): ProductFiscalProfileResource
    {
        return new ProductFiscalProfileResource($action->handle($variant, $request->validated()));
    }

    public function destroyForVariant(Product $product, ProductVariant $variant, DeleteProductFiscalProfile $action): Response
    {
        $profile = ProductFiscalProfile::query()
            ->where('fiscalizable_type', FiscalizableType::ProductVariant->value)
            ->where('fiscalizable_id', $variant->id)
            ->first();

        if ($profile !== null) {
            $action->handle($profile);
        }

        return response()->noContent();
    }

    private function show(FiscalizableType $type, string $fiscalizableId): JsonResponse|ProductFiscalProfileResource
    {
        $profile = ProductFiscalProfile::query()
            ->where('fiscalizable_type', $type->value)
            ->where('fiscalizable_id', $fiscalizableId)
            ->first();

        if ($profile === null) {
            return response()->json(['data' => null]);
        }

        return new ProductFiscalProfileResource($profile);
    }
}
