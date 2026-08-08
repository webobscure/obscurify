<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Locations\Models\Location;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\InventoryLevelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $store_id
 * @property string $inventory_item_id
 * @property string $location_id
 * @property int $on_hand
 * @property int $reserved
 */
class InventoryLevel extends Model
{
    /** @use HasFactory<InventoryLevelFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): InventoryLevelFactory
    {
        return InventoryLevelFactory::new();
    }

    protected $fillable = [
        'inventory_item_id',
        'location_id',
        'on_hand',
        'reserved',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
        ];
    }

    /**
     * Sellable quantity, derived rather than stored — see AdjustInventory
     * for the only place on_hand is mutated.
     */
    public function available(): int
    {
        return $this->on_hand - $this->reserved;
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
}
