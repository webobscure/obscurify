<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOptionValue>
 *
 * Deliberately has no `store_id` state: ProductOptionValue::creating()
 * always forces it from TenantContext, same as ProductFactory.
 */
class ProductOptionValueFactory extends Factory
{
    protected $model = ProductOptionValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_option_id' => ProductOption::factory(),
            'value' => fake()->unique()->word(),
            'position' => 0,
        ];
    }
}
