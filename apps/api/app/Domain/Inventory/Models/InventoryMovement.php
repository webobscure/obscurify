<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Locations\Models\Location;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit log entry — never updated after creation.
 *
 * @property string $id
 * @property string $store_id
 * @property string $inventory_item_id
 * @property string $location_id
 * @property int $quantity_delta
 * @property InventoryMovementReason $reason
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $created_by
 */
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): InventoryMovementFactory
    {
        return InventoryMovementFactory::new();
    }

    protected $fillable = [
        'inventory_item_id',
        'location_id',
        'quantity_delta',
        'reason',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'reason' => InventoryMovementReason::class,
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
