<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionProduct>
 *
 * Deliberately has no `store_id` state: CollectionProduct::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class CollectionProductFactory extends Factory
{
    protected $model = CollectionProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'product_id' => Product::factory(),
        ];
    }
}
