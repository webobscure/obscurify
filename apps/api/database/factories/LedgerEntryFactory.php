<?php

namespace Database\Factories;

use App\Domain\Financial\Enums\LedgerAccount;
use App\Domain\Financial\Enums\LedgerDirection;
use App\Domain\Financial\Models\LedgerEntry;
use App\Domain\Financial\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_transaction_id' => LedgerTransaction::factory(),
            'account' => LedgerAccount::Cash,
            'direction' => LedgerDirection::Debit,
            'currency' => 'RUB',
            'amount' => 1000,
        ];
    }
}
