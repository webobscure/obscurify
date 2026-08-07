<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 *
 * Deliberately has no `store_id` state: Product::creating() always forces
 * it from TenantContext. Wrap factory calls in
 * `app(TenantContext::class)->scope($store, fn () => Product::factory()->create())`
 * to create fixtures for a specific store.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 999999),
            'description' => fake()->sentence(),
            'status' => ProductStatus::Active,
        ];
    }
}
