<?php

namespace App\Domain\Financial\Models;

use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\RefundItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One refunded line, tied to a returned item — quantity is how much of
 * that ReturnItem this Refund covers (never more than what remains
 * unrefunded — ReturnItem.quantity minus already-refunded quantity
 * across every non-failed, non-cancelled Refund — see RequestRefund).
 *
 * @property string $id
 * @property string $store_id
 * @property string $refund_id
 * @property string $return_item_id
 * @property int $quantity
 * @property int $amount
 */
class RefundItem extends Model
{
    /** @use HasFactory<RefundItemFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): RefundItemFactory
    {
        return RefundItemFactory::new();
    }

    protected $fillable = [
        'refund_id',
        'return_item_id',
        'quantity',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => 'integer',
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
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return BelongsTo<ReturnItem, $this>
     */
    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ReturnItem::class);
    }
}
