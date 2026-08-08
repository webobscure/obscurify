<?php

namespace Database\Factories;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryReservation>
 *
 * Deliberately has no `store_id` state: InventoryReservation::creating()
 * always forces it from TenantContext, same as ProductFactory.
 */
class InventoryReservationFactory extends Factory
{
    protected $model = InventoryReservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'location_id' => Location::factory(),
            'checkout_id' => Checkout::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'status' => ReservationStatus::Active,
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
