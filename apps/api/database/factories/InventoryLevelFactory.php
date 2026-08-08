<?php

namespace Database\Factories;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLevel>
 *
 * Deliberately has no `store_id` state: InventoryLevel::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class InventoryLevelFactory extends Factory
{
    protected $model = InventoryLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'location_id' => Location::factory(),
            'on_hand' => 0,
            'reserved' => 0,
        ];
    }
}
