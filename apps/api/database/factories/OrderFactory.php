<?php

namespace Database\Factories;

use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Enums\FulfillmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 *
 * Deliberately has no `store_id` state: Order::creating() always forces
 * it from TenantContext, same as ProductFactory. `number` here is a
 * fake unique value for test fixtures only — real orders always get
 * theirs from AllocateOrderNumber, never this factory.
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1001, 999999),
            'currency' => 'RUB',
            'items_subtotal_amount' => 1000,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'order_status' => OrderStatus::Open,
            'financial_status' => FinancialStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
        ];
    }
}
