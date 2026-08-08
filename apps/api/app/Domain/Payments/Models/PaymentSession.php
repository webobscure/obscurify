<?php

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentSessionStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PaymentSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The storefront-visible payment initiation — what the browser is
 * actually redirected to. Kept separate from Payment so a retried
 * initiation doesn't lose the Payment's own identity/history.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string $payment_id
 * @property string $provider
 * @property PaymentSessionStatus $status
 * @property string|null $redirect_url
 * @property Carbon|null $expires_at
 */
class PaymentSession extends Model
{
    /** @use HasFactory<PaymentSessionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): PaymentSessionFactory
    {
        return PaymentSessionFactory::new();
    }

    protected $fillable = [
        'order_id',
        'payment_id',
        'provider',
        'status',
        'redirect_url',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentSessionStatus::class,
            'expires_at' => 'datetime',
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
}
