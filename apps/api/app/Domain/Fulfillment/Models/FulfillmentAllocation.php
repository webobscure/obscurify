<?php

namespace App\Domain\Fulfillment\Models;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Locations\Models\Location;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\FulfillmentAllocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Exactly which InventoryReservation (and therefore which Location) backs
 * a slice of a FulfillmentItem's quantity — see AllocateFulfillment for
 * the split-across-locations strategy, which mirrors ReserveInventory's.
 * Rows are never deleted (see migration docblock): consumed_at is set by
 * CompleteFulfillment, cancelled_at by CancelFulfillment, and an
 * allocation with neither set is still open/outstanding.
 *
 * @property string $id
 * @property string $store_id
 * @property string $fulfillment_item_id
 * @property string $location_id
 * @property string $inventory_item_id
 * @property string|null $inventory_reservation_id
 * @property int $quantity
 * @property Carbon|null $consumed_at
 * @property Carbon|null $cancelled_at
 */
class FulfillmentAllocation extends Model
{
    /** @use HasFactory<FulfillmentAllocationFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): FulfillmentAllocationFactory
    {
        return FulfillmentAllocationFactory::new();
    }

    protected $fillable = [
        'fulfillment_item_id',
        'location_id',
        'inventory_item_id',
        'inventory_reservation_id',
        'quantity',
        'consumed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'consumed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<FulfillmentItem, $this>
     */
    public function fulfillmentItem(): BelongsTo
    {
        return $this->belongsTo(FulfillmentItem::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * @return BelongsTo<InventoryReservation, $this>
     */
    public function inventoryReservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class);
    }
}
