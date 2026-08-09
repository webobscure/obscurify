<?php

namespace Database\Factories;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentItem>
 */
class ShipmentItemFactory extends Factory
{
    protected $model = ShipmentItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'order_item_id' => OrderItem::factory(),
            'quantity' => 1,
        ];
    }
}
