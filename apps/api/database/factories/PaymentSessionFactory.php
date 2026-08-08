<?php

namespace Database\Factories;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentSessionStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSession>
 *
 * Deliberately has no `store_id` state: PaymentSession::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class PaymentSessionFactory extends Factory
{
    protected $model = PaymentSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'payment_id' => Payment::factory(),
            'provider' => 'fake',
            'status' => PaymentSessionStatus::Pending,
        ];
    }
}
