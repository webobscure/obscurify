<?php

namespace App\Domain\Search\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Orders\Models\Order;
use App\Domain\Search\Enums\SearchAnalyticsEventType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string|null $search_query_id
 * @property SearchAnalyticsEventType $event_type
 * @property string|null $product_id
 * @property int|null $position
 * @property string|null $order_id
 * @property Carbon $occurred_at
 */
class SearchAnalyticsEvent extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'search_query_id',
        'event_type',
        'product_id',
        'position',
        'order_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => SearchAnalyticsEventType::class,
            'position' => 'integer',
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
     * @return BelongsTo<SearchQuery, $this>
     */
    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(SearchQuery::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
