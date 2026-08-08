<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 *
 * Deliberately has no `store_id` state: OrderItem::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->numberBetween(500, 5000);

        return [
            'order_id' => Order::factory(),
            'product_title' => fake()->words(3, true),
            'variant_title' => fake()->word(),
            'sku' => strtoupper(fake()->bothify('SKU-####??')),
            'unit_price_amount' => $unitPrice,
            'quantity' => $quantity,
            'line_total_amount' => $unitPrice * $quantity,
            'currency' => 'RUB',
        ];
    }
}
