<?php

namespace App\Domain\Orders\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot row — never updated after Order creation. product_id
 * / product_variant_id are nullOnDelete references for traceability only;
 * every other field is copied at order time specifically so a later
 * rename/price change/deletion of the live Product or Variant can never
 * change what this row reports.
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
 * @property string|null $product_id
 * @property string|null $product_variant_id
 * @property string $product_title
 * @property string|null $variant_title
 * @property string|null $sku
 * @property int $unit_price_amount
 * @property int $quantity
 * @property int $line_total_amount
 * @property string $currency
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_title',
        'variant_title',
        'sku',
        'unit_price_amount',
        'quantity',
        'line_total_amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_amount' => 'integer',
            'quantity' => 'integer',
            'line_total_amount' => 'integer',
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
     * Historical reference only — may be null if the Product was later
     * deleted. Never used to render the order; see class docblock.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
