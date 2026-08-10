<?php

namespace App\Domain\Financial\Models;

use App\Domain\Financial\Enums\LedgerAccount;
use App\Domain\Financial\Enums\LedgerDirection;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One debit or credit line against one account. Immutable — created_at
 * only, never updated. `amount` is always unsigned; `direction` carries
 * the sign, matching how a real ledger reads (spec section 5:
 * "Double-entry style").
 *
 * @property string $id
 * @property string $store_id
 * @property string $ledger_transaction_id
 * @property LedgerAccount $account
 * @property LedgerDirection $direction
 * @property string $currency
 * @property int $amount
 */
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): LedgerEntryFactory
    {
        return LedgerEntryFactory::new();
    }

    protected $fillable = [
        'ledger_transaction_id',
        'account',
        'direction',
        'currency',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'account' => LedgerAccount::class,
            'direction' => LedgerDirection::class,
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }
}
