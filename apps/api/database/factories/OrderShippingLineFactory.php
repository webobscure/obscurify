<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderShippingLine;
use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderShippingLine>
 */
class OrderShippingLineFactory extends Factory
{
    protected $model = OrderShippingLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => FakeShippingProvider::CODE,
            'service_code' => 'standard',
            'title' => 'Standard Shipping',
            'price_amount' => 50000,
            'currency' => 'RUB',
            'estimated_days_min' => 3,
            'estimated_days_max' => 5,
        ];
    }
}
