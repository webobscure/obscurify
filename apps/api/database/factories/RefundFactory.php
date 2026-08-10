<?php

namespace Database\Factories;

use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Financial\Models\Refund;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'payment_id' => Payment::factory(),
            'number' => $this->faker->unique()->numberBetween(1001, 999999),
            'status' => RefundStatus::Requested,
            'currency' => 'RUB',
            'amount' => 1000,
            'requested_at' => now(),
        ];
    }
}
