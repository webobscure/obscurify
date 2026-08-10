<?php

namespace App\Domain\Financial\Models;

use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One refund attempt against a Payment — multiple may exist per Order/
 * Payment (spec section 2), each independently lifecycled. `amount` is
 * always the sum of `refund_items.amount` + `shipping_amount` +
 * `adjustment_amount` (enforced in RequestRefund, never trusted from the
 * client beyond the individual components). `provider` null means a
 * manual refund (spec section 11) — no provider call was ever made, the
 * refund still completes and posts ledger entries synchronously.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string $payment_id
 * @property int $number
 * @property RefundStatus $status
 * @property string $currency
 * @property int $amount
 * @property int $shipping_amount
 * @property int $adjustment_amount
 * @property string|null $reason
 * @property string|null $provider
 * @property string|null $provider_reference
 * @property Carbon $requested_at
 * @property Carbon|null $processed_at
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): RefundFactory
    {
        return RefundFactory::new();
    }

    protected $fillable = [
        'order_id',
        'payment_id',
        'number',
        'status',
        'currency',
        'amount',
        'shipping_amount',
        'adjustment_amount',
        'reason',
        'provider',
        'provider_reference',
        'requested_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => RefundStatus::class,
            'amount' => 'integer',
            'shipping_amount' => 'integer',
            'adjustment_amount' => 'integer',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return HasMany<RefundItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }
}
