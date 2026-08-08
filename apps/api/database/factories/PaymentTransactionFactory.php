<?php

namespace Database\Factories;

use App\Domain\Payments\Enums\PaymentTransactionStatus;
use App\Domain\Payments\Enums\PaymentTransactionType;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTransaction>
 *
 * Deliberately has no `store_id` state: PaymentTransaction::creating()
 * always forces it from TenantContext, same as ProductFactory.
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'type' => PaymentTransactionType::Webhook,
            'status' => PaymentTransactionStatus::Succeeded,
            'currency' => 'RUB',
            'amount' => 1000,
        ];
    }
}
