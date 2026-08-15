<?php

namespace App\Domain\CustomerIntelligence\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The customer's *current* computed metrics — one row, upserted by
 * RecomputeCustomerMetrics. See the migration's docblock for the exact
 * definition of each field and why return_rate is stored in basis
 * points.
 *
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property int $total_spent_amount
 * @property int $average_order_value_amount
 * @property int $order_count
 * @property int $refund_count
 * @property int $return_count
 * @property int $return_rate_bps
 * @property int $lifetime_value_amount
 * @property string|null $currency
 * @property Carbon|null $first_order_at
 * @property Carbon|null $last_order_at
 * @property Carbon $computed_at
 */
class CustomerMetric extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'customer_id',
        'total_spent_amount',
        'average_order_value_amount',
        'order_count',
        'refund_count',
        'return_count',
        'return_rate_bps',
        'lifetime_value_amount',
        'currency',
        'first_order_at',
        'last_order_at',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_spent_amount' => 'integer',
            'average_order_value_amount' => 'integer',
            'order_count' => 'integer',
            'refund_count' => 'integer',
            'return_count' => 'integer',
            'return_rate_bps' => 'integer',
            'lifetime_value_amount' => 'integer',
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'computed_at' => 'datetime',
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

    public function daysSinceLastOrder(): ?int
    {
        $days = $this->last_order_at?->diffInDays(now());

        return $days === null ? null : (int) $days;
    }
}
