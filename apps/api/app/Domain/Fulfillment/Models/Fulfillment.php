<?php

namespace App\Domain\Fulfillment\Models;

use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\FulfillmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One Fulfillment is one warehouse "pick list" against an Order — multiple
 * may exist per Order (partial fulfillment, spec section 11). Independent
 * of Shipping: this model has no knowledge of ShippingProviderContract or
 * any Shipping-owned table; Shipment is the one place downstream (Shipping
 * domain) that references a Fulfillment back, not the reverse.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property FulfillmentStatus $status
 * @property string|null $notes
 * @property string|null $created_by
 * @property Carbon|null $completed_at
 */
class Fulfillment extends Model
{
    /** @use HasFactory<FulfillmentFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): FulfillmentFactory
    {
        return FulfillmentFactory::new();
    }

    protected $fillable = [
        'order_id',
        'status',
        'notes',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FulfillmentStatus::class,
            'completed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<FulfillmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FulfillmentItem::class);
    }

    /**
     * @return HasMany<FulfillmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(FulfillmentEvent::class);
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
