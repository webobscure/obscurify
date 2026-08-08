<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentTransactionStatus;
use App\Domain\Payments\Enums\PaymentTransactionType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable, append-only transaction ledger — never updated after
 * creation (same convention as OrderItem/OrderAddress). Every provider
 * event/state change appends a new row rather than mutating history.
 *
 * @property string $id
 * @property string $store_id
 * @property string $payment_id
 * @property PaymentTransactionType $type
 * @property PaymentTransactionStatus $status
 * @property string $currency
 * @property int $amount
 * @property string|null $external_transaction_id
 * @property array<string, mixed>|null $metadata
 */
class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): PaymentTransactionFactory
    {
        return PaymentTransactionFactory::new();
    }

    protected $fillable = [
        'payment_id',
        'type',
        'status',
        'currency',
        'amount',
        'external_transaction_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentTransactionType::class,
            'status' => PaymentTransactionStatus::class,
            'amount' => 'integer',
            'metadata' => 'array',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
