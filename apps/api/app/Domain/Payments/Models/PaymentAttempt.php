<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per attempt to initiate a provider payment — never mutated
 * into a different attempt; a retried initiation gets its own row.
 * Deliberately holds no secrets/credentials, only safe provider-facing
 * metadata (see spec section 6).
 *
 * @property string $id
 * @property string $store_id
 * @property string $payment_id
 * @property string|null $payment_session_id
 * @property string $provider
 * @property PaymentAttemptStatus $status
 * @property string|null $external_attempt_id
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 */
class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): PaymentAttemptFactory
    {
        return PaymentAttemptFactory::new();
    }

    protected $fillable = [
        'payment_id',
        'payment_session_id',
        'provider',
        'status',
        'external_attempt_id',
        'error_code',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
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

    /**
     * @return BelongsTo<PaymentSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PaymentSession::class, 'payment_session_id');
    }
}
