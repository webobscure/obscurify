<?php

namespace Database\Factories;

use App\Domain\Fulfillment\Models\FulfillmentAllocation;
use App\Domain\Fulfillment\Models\FulfillmentItem;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentAllocation>
 */
class FulfillmentAllocationFactory extends Factory
{
    protected $model = FulfillmentAllocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fulfillment_item_id' => FulfillmentItem::factory(),
            'location_id' => Location::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'inventory_reservation_id' => null,
            'quantity' => 1,
        ];
    }
}
