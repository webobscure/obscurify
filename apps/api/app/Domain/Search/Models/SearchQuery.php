<?php

namespace App\Domain\Search\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per executed search — the search-analytics source of truth
 * for "most popular searches" (group by normalized_query) and
 * "top failed searches" (where result_count = 0) — spec section 12.
 *
 * @property string $id
 * @property string $store_id
 * @property string $query_text
 * @property string $normalized_query
 * @property array<string, mixed>|null $filters
 * @property string|null $sort
 * @property int $result_count
 * @property string|null $customer_id
 * @property string|null $session_id
 */
class SearchQuery extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'query_text',
        'normalized_query',
        'filters',
        'sort',
        'result_count',
        'customer_id',
        'session_id',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'result_count' => 'integer',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SearchAnalyticsEvent, $this>
     */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(SearchAnalyticsEvent::class);
    }
}
