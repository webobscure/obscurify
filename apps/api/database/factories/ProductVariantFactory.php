<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 *
 * Deliberately has no `store_id` state: ProductVariant::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'title' => fake()->words(2, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'price_amount' => fake()->numberBetween(1000, 999999),
            'currency' => 'RUB',
            'status' => ProductStatus::Active,
        ];
    }
}
