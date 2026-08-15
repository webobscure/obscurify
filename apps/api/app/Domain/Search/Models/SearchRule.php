<?php

namespace App\Domain\Search\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Enums\SearchRuleAction;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A boost/hide merchandising rule (spec section 10) — `keyword` null
 * means "every search." Executed by SearchMerchandisingEngine after
 * the provider's own text-relevance search, before ranking/pagination.
 *
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string|null $keyword
 * @property SearchRuleAction $action
 * @property string $product_id
 * @property int|null $boost_amount
 * @property bool $is_active
 * @property int $position
 */
class SearchRule extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'name',
        'keyword',
        'action',
        'product_id',
        'boost_amount',
        'is_active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'action' => SearchRuleAction::class,
            'boost_amount' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
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
