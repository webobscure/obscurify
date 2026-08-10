<?php

namespace App\Domain\Returns\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ReturnRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One customer-or-merchant-initiated return attempt against an Order —
 * multiple may exist per Order (spec section 2), each independently
 * lifecycled. Independent of Payments/Shipping/Fulfillment: this domain
 * has no knowledge of PaymentProviderContract, ShippingProviderContract,
 * or Fulfillment's allocation machinery — it reads OrderItem/ShipmentItem
 * to validate returnable quantity and writes InventoryMovement directly,
 * the same boundary Fulfillment itself uses toward Inventory.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string|null $customer_id
 * @property int $number
 * @property ReturnStatus $status
 * @property Carbon $requested_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $received_at
 * @property Carbon|null $closed_at
 * @property string|null $notes
 */
class ReturnRequest extends Model
{
    /** @use HasFactory<ReturnRequestFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): ReturnRequestFactory
    {
        return ReturnRequestFactory::new();
    }

    protected $fillable = [
        'order_id',
        'customer_id',
        'number',
        'status',
        'requested_at',
        'approved_at',
        'received_at',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => ReturnStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'closed_at' => 'datetime',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<ReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    /**
     * @return HasMany<ReturnEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ReturnEvent::class);
    }
}
