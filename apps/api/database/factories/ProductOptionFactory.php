<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOption>
 *
 * Deliberately has no `store_id` state: ProductOption::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class ProductOptionFactory extends Factory
{
    protected $model = ProductOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->unique()->randomElement(['Color', 'Size', 'Material', 'Style']),
            'position' => 0,
        ];
    }
}
