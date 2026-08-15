<?php

namespace App\Domain\CustomerIntelligence\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property array<string, mixed> $metrics
 */
class CustomerSnapshot extends Model
{
    use BelongsToTenant, HasUlids;

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'metrics',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'captured_at' => 'datetime',
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
