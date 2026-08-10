<?php

namespace Database\Factories;

use App\Domain\Financial\Models\LedgerTransaction;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LedgerTransaction>
 */
class LedgerTransactionFactory extends Factory
{
    protected $model = LedgerTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reference_type' => Payment::class,
            'reference_id' => (string) Str::ulid(),
            'occurred_at' => now(),
        ];
    }
}
