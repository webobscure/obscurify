<?php

namespace App\Domain\Customers\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CustomerSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One row per logged-in device/browser — what a "your active sessions"
 * screen lists and what logout/logout-everywhere revokes. Distinct from
 * CustomerAccessToken: several tokens (an access/refresh pair, then its
 * rotated successors) belong to one session over its lifetime.
 *
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $last_used_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 */
class CustomerSession extends Model
{
    /** @use HasFactory<CustomerSessionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    const UPDATED_AT = null;

    protected static function newFactory(): CustomerSessionFactory
    {
        return CustomerSessionFactory::new();
    }

    protected $fillable = [
        'customer_id',
        'ip_address',
        'user_agent',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
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
     * @return HasMany<CustomerAccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(CustomerAccessToken::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked();
    }
}
