<?php

namespace App\Domain\Promotions\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Promotions\Enums\DiscountApplicationTarget;
use App\Domain\Promotions\Enums\PromotionActionType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Order's immutable discount snapshot — see discount_applications
 * migration for why promotion_name/code are copied rather than read live
 * through promotion_id/discount_code_id.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string|null $promotion_id
 * @property string|null $discount_code_id
 * @property string|null $order_item_id
 * @property string $promotion_name
 * @property string|null $code
 * @property PromotionActionType $action_type
 * @property DiscountApplicationTarget $target
 * @property int $amount
 * @property string $currency
 */
class DiscountApplication extends Model
{
    use BelongsToTenant, HasUlids;

    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'promotion_id',
        'discount_code_id',
        'order_item_id',
        'promotion_name',
        'code',
        'action_type',
        'target',
        'amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => PromotionActionType::class,
            'target' => DiscountApplicationTarget::class,
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * @return BelongsTo<DiscountCode, $this>
     */
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
