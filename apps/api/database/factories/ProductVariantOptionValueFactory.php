<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductOptionValue;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Models\ProductVariantOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariantOptionValue>
 *
 * Deliberately has no `store_id` state: ProductVariantOptionValue::creating()
 * always forces it from TenantContext, same as ProductFactory.
 */
class ProductVariantOptionValueFactory extends Factory
{
    protected $model = ProductVariantOptionValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'product_option_value_id' => ProductOptionValue::factory(),
        ];
    }
}
