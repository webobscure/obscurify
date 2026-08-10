<?php

namespace App\Domain\Financial\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\FinancialEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only, unified per-order financial timeline (spec section 16) —
 * mirrors ReturnEvent/FulfillmentEvent exactly, but scoped to `order_id`
 * rather than to one Payment/Refund, since spec's own example events
 * ("Payment captured", "Refund requested", "Refund completed", "Ledger
 * created") span both.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string $type
 * @property string|null $description
 * @property Carbon $occurred_at
 */
class FinancialEvent extends Model
{
    /** @use HasFactory<FinancialEventFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): FinancialEventFactory
    {
        return FinancialEventFactory::new();
    }

    protected $fillable = [
        'order_id',
        'type',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
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
}
