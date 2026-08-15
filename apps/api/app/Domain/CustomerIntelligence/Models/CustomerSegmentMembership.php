<?php

namespace App\Domain\CustomerIntelligence\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row's mere existence *is* the membership state — see the migration's
 * docblock. Never written to directly outside RecomputeCustomerMetrics.
 *
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property string $segmentable_type
 * @property string $segmentable_id
 */
class CustomerSegmentMembership extends Model
{
    use BelongsToTenant, HasUlids;

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'segmentable_type',
        'segmentable_id',
        'entered_at',
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
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
}
