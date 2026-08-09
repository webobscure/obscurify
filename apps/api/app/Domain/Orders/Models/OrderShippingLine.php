<?php

namespace App\Domain\Orders\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\OrderShippingLineFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot row, same shape and same reasoning as OrderItem: never
 * updated after Order creation, so a later ShippingMethod rename/price
 * change/deletion can never change what a past order reports (spec
 * section 14).
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string $provider
 * @property string|null $service_code
 * @property string $title
 * @property int $price_amount
 * @property string $currency
 * @property int|null $estimated_days_min
 * @property int|null $estimated_days_max
 */
class OrderShippingLine extends Model
{
    /** @use HasFactory<OrderShippingLineFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): OrderShippingLineFactory
    {
        return OrderShippingLineFactory::new();
    }

    protected $fillable = [
        'order_id',
        'provider',
        'service_code',
        'title',
        'price_amount',
        'currency',
        'estimated_days_min',
        'estimated_days_max',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'estimated_days_min' => 'integer',
            'estimated_days_max' => 'integer',
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
