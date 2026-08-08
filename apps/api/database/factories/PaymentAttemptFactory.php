<?php

namespace Database\Factories;

use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 *
 * Deliberately has no `store_id` state: PaymentAttempt::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'provider' => 'fake',
            'status' => PaymentAttemptStatus::Succeeded,
        ];
    }
}
