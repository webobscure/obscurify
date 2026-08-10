<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnRequest>
 */
class ReturnRequestFactory extends Factory
{
    protected $model = ReturnRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'number' => $this->faker->unique()->numberBetween(1001, 999999),
            'status' => ReturnStatus::Requested,
            'requested_at' => now(),
        ];
    }
}
