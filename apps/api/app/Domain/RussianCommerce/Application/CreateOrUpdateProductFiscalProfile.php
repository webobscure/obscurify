<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\RussianCommerce\Enums\FiscalizableType;
use App\Domain\RussianCommerce\Models\ProductFiscalProfile;

/**
 * Spec section 12 — one profile per (fiscalizable_type, fiscalizable_id),
 * upserted. `Product|ProductVariant $fiscalizable` decides the type
 * tag itself rather than trusting a caller-supplied string.
 */
final class CreateOrUpdateProductFiscalProfile
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Product|ProductVariant $fiscalizable, array $data): ProductFiscalProfile
    {
        $type = $fiscalizable instanceof Product ? FiscalizableType::Product : FiscalizableType::ProductVariant;

        return ProductFiscalProfile::query()->updateOrCreate(
            ['fiscalizable_type' => $type->value, 'fiscalizable_id' => $fiscalizable->id],
            $data,
        );
    }
}
