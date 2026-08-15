<?php

namespace App\Domain\Customers\Models;

use App\Domain\Customers\Enums\CustomerActionTokenPurpose;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Single-use, hashed, expiring token backing password reset and email
 * verification — see the create-table migration's docblock for why this
 * is one table with a `purpose` column rather than two.
 *
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property CustomerActionTokenPurpose $purpose
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
class CustomerActionToken extends Model
{
    use BelongsToTenant, HasUlids;

    const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'purpose',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => CustomerActionTokenPurpose::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
