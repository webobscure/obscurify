<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string $fulfillment_id
 * @property string $provider
 * @property string|null $external_shipment_id
 * @property ShipmentStatus $status
 * @property string|null $tracking_number
 * @property string|null $tracking_url
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 * @property array<string, mixed>|null $metadata
 */
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): ShipmentFactory
    {
        return ShipmentFactory::new();
    }

    protected $fillable = [
        'order_id',
        'fulfillment_id',
        'provider',
        'external_shipment_id',
        'status',
        'tracking_number',
        'tracking_url',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Fulfillment, $this>
     */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    /**
     * @return HasMany<ShipmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * @return HasMany<TrackingEvent, $this>
     */
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }
}
