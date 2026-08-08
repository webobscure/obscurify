<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Media\Enums\MediaEntityType;
use App\Domain\Media\Models\Media;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $store_id
 * @property string $product_id
 * @property string $title
 * @property string|null $sku
 * @property string|null $barcode
 * @property int $price_amount
 * @property int|null $compare_at_price_amount
 * @property int|null $cost_amount
 * @property string $currency
 * @property string|null $weight
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property string $option_signature
 * @property ProductStatus $status
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    protected $fillable = [
        'product_id',
        'title',
        'sku',
        'barcode',
        'price_amount',
        'compare_at_price_amount',
        'cost_amount',
        'currency',
        'weight',
        'length',
        'width',
        'height',
        'option_signature',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'compare_at_price_amount' => 'integer',
            'cost_amount' => 'integer',
            'status' => ProductStatus::class,
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductVariantOptionValue, $this>
     */
    public function optionValueLinks(): HasMany
    {
        return $this->hasMany(ProductVariantOptionValue::class);
    }

    /**
     * Read-only convenience relation for eager loading — writes always go
     * through CreateVariant/UpdateVariant so combination invariants and
     * tenant checks are enforced.
     *
     * @return BelongsToMany<ProductOptionValue, $this>
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_option_values',
        )->withTimestamps();
    }

    /**
     * @return HasOne<InventoryItem, $this>
     */
    public function inventoryItem(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }

    /**
     * The variant's own media, if any. Falls back to the parent product's
     * media in the UI/API layer, not here — this relation only returns
     * media explicitly attached to the variant itself.
     *
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'entity_id')
            ->where('entity_type', MediaEntityType::ProductVariant)
            ->orderBy('position');
    }
}
