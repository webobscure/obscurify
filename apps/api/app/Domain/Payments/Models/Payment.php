<?php

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\RussianCommerce\Enums\RussianPaymentMethod;
use App\Domain\RussianCommerce\Models\FiscalReceipt;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string $provider
 * @property PaymentStatus $status
 * @property string $currency
 * @property int $amount
 * @property int $authorized_amount
 * @property int $captured_amount
 * @property int $refunded_amount
 * @property string|null $external_payment_id
 * @property array<string, mixed>|null $metadata
 * @property RussianPaymentMethod|null $payment_method
 * @property array<string, mixed>|null $method_metadata
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    protected $fillable = [
        'order_id',
        'provider',
        'status',
        'currency',
        'amount',
        'authorized_amount',
        'captured_amount',
        'refunded_amount',
        'external_payment_id',
        'metadata',
        'payment_method',
        'method_metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'authorized_amount' => 'integer',
            'captured_amount' => 'integer',
            'refunded_amount' => 'integer',
            'metadata' => 'array',
            'payment_method' => RussianPaymentMethod::class,
            'method_metadata' => 'array',
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<PaymentSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(PaymentSession::class);
    }

    /**
     * @return HasMany<PaymentAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Russian Commerce Foundation (spec section 17: fiscalization status
     * on Payment details) — a payment has at most one FiscalReceipt,
     * even though FiscalReceipt.payment_id isn't unique-constrained
     * (a future correction/refund receipt would share the same
     * payment_id, see FiscalReceiptOperation).
     *
     * @return HasOne<FiscalReceipt, $this>
     */
    public function fiscalReceipt(): HasOne
    {
        return $this->hasOne(FiscalReceipt::class)->latestOfMany();
    }
}
