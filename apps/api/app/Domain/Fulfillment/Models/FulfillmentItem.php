<?php

namespace App\Domain\Fulfillment\Models;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\FulfillmentItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * quantity is how much of this OrderItem this Fulfillment is attempting
 * (never more than what remains unfulfilled — see CreateFulfillment).
 * picked_quantity/packed_quantity track warehouse progress; the class
 * itself does not enforce picked <= quantity / packed <= picked, since
 * that must be checked under a row lock at write time (see
 * PickFulfillmentItems/PackFulfillmentItems), not on every model read.
 *
 * @property string $id
 * @property string $store_id
 * @property string $fulfillment_id
 * @property string $order_item_id
 * @property int $quantity
 * @property int $picked_quantity
 * @property int $packed_quantity
 */
class FulfillmentItem extends Model
{
    /** @use HasFactory<FulfillmentItemFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): FulfillmentItemFactory
    {
        return FulfillmentItemFactory::new();
    }

    protected $fillable = [
        'fulfillment_id',
        'order_item_id',
        'quantity',
        'picked_quantity',
        'packed_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'picked_quantity' => 'integer',
            'packed_quantity' => 'integer',
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
     * @return BelongsTo<Fulfillment, $this>
     */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return HasMany<FulfillmentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(FulfillmentAllocation::class);
    }
}
