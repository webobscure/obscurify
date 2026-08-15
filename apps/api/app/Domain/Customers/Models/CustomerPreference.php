<?php

namespace App\Domain\Customers\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Free-form key/value store for per-customer preferences (marketing
 * opt-in, locale, etc.) — the same shape as ThemeSetting, deliberately
 * schemaless rather than a fixed column per preference so a new
 * preference never requires a migration.
 *
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property string $key
 * @property mixed $value
 */
class CustomerPreference extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'customer_id',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
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
