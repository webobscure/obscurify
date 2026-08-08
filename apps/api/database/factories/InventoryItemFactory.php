<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 *
 * Deliberately has no `store_id` state: InventoryItem::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'tracked' => true,
            'requires_shipping' => true,
        ];
    }
}
