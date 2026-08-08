<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 *
 * Deliberately has no `store_id` state: Payment::creating() always forces
 * it from TenantContext, same as ProductFactory.
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'fake',
            'status' => PaymentStatus::Pending,
            'currency' => 'RUB',
            'amount' => 1000,
            'authorized_amount' => 0,
            'captured_amount' => 0,
            'refunded_amount' => 0,
        ];
    }
}
