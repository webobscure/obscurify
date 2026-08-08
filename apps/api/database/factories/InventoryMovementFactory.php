<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 *
 * Deliberately has no `store_id` state: InventoryMovement::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'location_id' => Location::factory(),
            'quantity_delta' => fake()->numberBetween(-10, 10),
            'reason' => InventoryMovementReason::ManualAdjustment,
            'reference_type' => null,
            'reference_id' => null,
            'created_by' => null,
        ];
    }
}
