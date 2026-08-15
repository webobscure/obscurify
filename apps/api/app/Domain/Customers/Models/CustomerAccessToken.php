<?php

namespace App\Domain\Customers\Models;

use App\Domain\Customers\Enums\CustomerTokenType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mirrors AppToken exactly — see IssueCustomerTokenPair for why (the same
 * proven access/refresh + rotation-chain shape from the Apps OAuth token
 * system, reused for customer auth rather than reinvented).
 *
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property string $customer_session_id
 * @property string|null $rotated_from_id
 * @property CustomerTokenType $type
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 */
class CustomerAccessToken extends Model
{
    use BelongsToTenant, HasUlids;

    const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'customer_session_id',
        'rotated_from_id',
        'type',
        'token_hash',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerTokenType::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
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
     * @return BelongsTo<CustomerSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CustomerSession::class, 'customer_session_id');
    }

    /**
     * @return BelongsTo<CustomerAccessToken, $this>
     */
    public function rotatedFrom(): BelongsTo
    {
        return $this->belongsTo(CustomerAccessToken::class, 'rotated_from_id');
    }
}
