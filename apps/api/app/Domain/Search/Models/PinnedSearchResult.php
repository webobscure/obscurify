<?php

namespace App\Domain\Search\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Always show this product at this position for this keyword" — a
 * pin always wins over ranking score, unlike SearchRule's Boost (a
 * score delta that can still be outranked).
 *
 * @property string $id
 * @property string $store_id
 * @property string $keyword
 * @property string $product_id
 * @property int $position
 * @property bool $is_active
 */
class PinnedSearchResult extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'keyword',
        'product_id',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
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
