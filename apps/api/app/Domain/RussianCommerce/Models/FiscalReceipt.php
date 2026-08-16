<?php

namespace App\Domain\RussianCommerce\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\RussianCommerce\Enums\FiscalReceiptOperation;
use App\Domain\RussianCommerce\Enums\FiscalReceiptStatus;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Spec section 5 — the provider-neutral fiscal receipt. Status is its
 * own lifecycle (FiscalReceiptStatus), never derived from or driving
 * Payment.status (spec sections 7/15) — see FiscalizationSubscriber and
 * ProcessFiscalizationCallback for how the two domains stay decoupled.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string|null $payment_id
 * @property string|null $correction_of_id
 * @property FiscalReceiptOperation $operation
 * @property FiscalReceiptStatus $status
 * @property string $provider
 * @property string|null $external_receipt_id
 * @property string $seller_inn
 * @property string|null $seller_kpp
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string $currency
 * @property int $total_amount
 * @property Carbon|null $fiscalized_at
 * @property string|null $error_message
 * @property int $attempt_count
 */
class FiscalReceipt extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'order_id',
        'payment_id',
        'correction_of_id',
        'operation',
        'status',
        'provider',
        'external_receipt_id',
        'seller_inn',
        'seller_kpp',
        'customer_email',
        'customer_phone',
        'currency',
        'total_amount',
        'fiscalized_at',
        'error_message',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'operation' => FiscalReceiptOperation::class,
            'status' => FiscalReceiptStatus::class,
            'total_amount' => 'integer',
            'fiscalized_at' => 'datetime',
            'attempt_count' => 'integer',
        ];
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
     * @return BelongsTo<FiscalReceipt, $this>
     */
    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    /**
     * @return HasMany<FiscalReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FiscalReceiptItem::class);
    }
}
