<?php

namespace Database\Factories;

use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Fulfillment\Models\FulfillmentItem;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentItem>
 */
class FulfillmentItemFactory extends Factory
{
    protected $model = FulfillmentItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fulfillment_id' => Fulfillment::factory(),
            'order_item_id' => OrderItem::factory(),
            'quantity' => 1,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
        ];
    }
}
