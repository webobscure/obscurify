<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ProductVariantOptionValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a ProductVariant to one selected ProductOptionValue.
 *
 * @property int $id
 * @property string $store_id
 * @property string $product_variant_id
 * @property string $product_option_value_id
 */
class ProductVariantOptionValue extends Model
{
    /** @use HasFactory<ProductVariantOptionValueFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ProductVariantOptionValueFactory
    {
        return ProductVariantOptionValueFactory::new();
    }

    protected $fillable = [
        'product_variant_id',
        'product_option_value_id',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<ProductOptionValue, $this>
     */
    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(ProductOptionValue::class, 'product_option_value_id');
    }
}
