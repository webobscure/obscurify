<?php

namespace App\Domain\Search\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The indexed, denormalized representation of one product — see the
 * migration's own docblock and docs/architecture/search.md §3. Every
 * search/facet/autocomplete read goes through this table, never
 * Product/ProductVariant/InventoryLevel directly.
 *
 * @property string $id
 * @property string $store_id
 * @property string $product_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $vendor
 * @property string|null $product_type
 * @property array<int, string>|null $tags
 * @property array<int, string>|null $collection_ids
 * @property array<int, string>|null $category_ids
 * @property array<int, array{option: string, value: string}>|null $variant_option_values
 * @property int|null $price_min
 * @property int|null $price_max
 * @property string|null $currency
 * @property bool $availability
 * @property int $inventory_quantity
 * @property string $status
 * @property bool $is_searchable
 * @property string|null $thumbnail_url
 * @property int $popularity
 * @property int $sales_count
 * @property int $search_score
 * @property string $search_text
 * @property Carbon $product_created_at
 * @property Carbon $product_updated_at
 */
class SearchDocument extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'product_id',
        'title',
        'slug',
        'description',
        'vendor',
        'product_type',
        'tags',
        'collection_ids',
        'category_ids',
        'variant_option_values',
        'price_min',
        'price_max',
        'currency',
        'availability',
        'inventory_quantity',
        'status',
        'is_searchable',
        'thumbnail_url',
        'popularity',
        'sales_count',
        'search_score',
        'search_text',
        'product_created_at',
        'product_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'collection_ids' => 'array',
            'category_ids' => 'array',
            'variant_option_values' => 'array',
            'price_min' => 'integer',
            'price_max' => 'integer',
            'availability' => 'boolean',
            'inventory_quantity' => 'integer',
            'is_searchable' => 'boolean',
            'popularity' => 'integer',
            'sales_count' => 'integer',
            'search_score' => 'integer',
            'product_created_at' => 'datetime',
            'product_updated_at' => 'datetime',
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
}
