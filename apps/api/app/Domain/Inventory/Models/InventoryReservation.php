<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Locations\Models\Location;
use App\Domain\Orders\Models\Order;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\InventoryReservationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per (InventoryItem, Location) actually allocated to a checkout
 * — see ReserveInventory for the split-allocation strategy.
 *
 * @property string $id
 * @property string $store_id
 * @property string $inventory_item_id
 * @property string $location_id
 * @property string $checkout_id
 * @property string|null $order_id
 * @property int $quantity
 * @property ReservationStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $released_at
 * @property Carbon|null $consumed_at
 */
class InventoryReservation extends Model
{
    /** @use HasFactory<InventoryReservationFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): InventoryReservationFactory
    {
        return InventoryReservationFactory::new();
    }

    protected $fillable = [
        'inventory_item_id',
        'location_id',
        'checkout_id',
        'order_id',
        'quantity',
        'status',
        'expires_at',
        'released_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => ReservationStatus::class,
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'consumed_at' => 'datetime',
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
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Checkout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
