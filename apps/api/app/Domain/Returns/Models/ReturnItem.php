<?php

namespace App\Domain\Returns\Models;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Enums\ReturnReason;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ReturnItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * quantity is how much of this OrderItem is being returned (never more
 * than what remains returnable — shipped minus already-returned — see
 * RequestReturn). `condition` here is the claim made when the return was
 * requested, unverified; ReturnInspection.condition (reachable via
 * `inspection`) is the merchant's verified assessment after physically
 * examining the item.
 *
 * @property string $id
 * @property string $store_id
 * @property string $return_request_id
 * @property string $order_item_id
 * @property int $quantity
 * @property ReturnReason $reason
 * @property ReturnCondition|null $condition
 * @property string|null $notes
 */
class ReturnItem extends Model
{
    /** @use HasFactory<ReturnItemFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): ReturnItemFactory
    {
        return ReturnItemFactory::new();
    }

    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity',
        'reason',
        'condition',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reason' => ReturnReason::class,
            'condition' => ReturnCondition::class,
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
     * @return BelongsTo<ReturnRequest, $this>
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return HasOne<ReturnInspection, $this>
     */
    public function inspection(): HasOne
    {
        return $this->hasOne(ReturnInspection::class);
    }

    /**
     * @return HasOne<ReturnDisposition, $this>
     */
    public function disposition(): HasOne
    {
        return $this->hasOne(ReturnDisposition::class);
    }
}
