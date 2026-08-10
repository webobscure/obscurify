<?php

namespace Database\Factories;

use App\Domain\Financial\Models\FinancialEvent;
use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialEvent>
 */
class FinancialEventFactory extends Factory
{
    protected $model = FinancialEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'type' => 'payment_captured',
            'occurred_at' => now(),
        ];
    }
}
