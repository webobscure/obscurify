<?php

namespace Database\Factories;

use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fulfillment>
 */
class FulfillmentFactory extends Factory
{
    protected $model = Fulfillment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => FulfillmentStatus::Pending,
        ];
    }
}
