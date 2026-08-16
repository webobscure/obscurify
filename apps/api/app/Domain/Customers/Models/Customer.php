<?php

namespace App\Domain\Customers\Models;

use App\Domain\CustomerIntelligence\Models\CustomerGroupMember;
use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\CustomerIntelligence\Models\CustomerSnapshot;
use App\Domain\CustomerIntelligence\Models\CustomerTagAssignment;
use App\Domain\Customers\Enums\CustomerStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A CRM/e-commerce record. Historically (through Milestone 15) had no
 * login identity at all; Milestone 16 adds real authentication *on top*
 * via a separate CustomerIdentity row rather than columns on this model
 * — see docs/adr/022-customer-identity.md. `email`/`first_name`/etc.
 * here remain a denormalized profile, editable and not unique; the
 * actual login credential (hashed password, lockout state) lives on
 * CustomerIdentity, keyed by (store, type, normalized identifier).
 *
 * @property string $id
 * @property string $store_id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $locale
 * @property Carbon|null $date_of_birth
 * @property string|null $first_name
 * @property string|null $last_name
 * @property CustomerStatus $status
 * @property Carbon|null $verified_at
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected $fillable = [
        'email',
        'phone',
        'locale',
        'date_of_birth',
        'first_name',
        'last_name',
        'status',
        'verified_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'metadata' => 'array',
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
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * @return HasOne<CustomerIdentity, $this>
     */
    public function identity(): HasOne
    {
        return $this->hasOne(CustomerIdentity::class);
    }

    /**
     * @return HasMany<CustomerSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CustomerSession::class);
    }

    /**
     * @return HasMany<CustomerAccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(CustomerAccessToken::class);
    }

    /**
     * @return HasMany<CustomerPreference, $this>
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(CustomerPreference::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<ReturnRequest, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /**
     * @return HasOne<CustomerMetric, $this>
     */
    public function metric(): HasOne
    {
        return $this->hasOne(CustomerMetric::class);
    }

    /**
     * @return HasMany<CustomerSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(CustomerSnapshot::class);
    }

    /**
     * @return HasMany<CustomerTagAssignment, $this>
     */
    public function tagAssignments(): HasMany
    {
        return $this->hasMany(CustomerTagAssignment::class);
    }

    /**
     * @return HasMany<CustomerGroupMember, $this>
     */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(CustomerGroupMember::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }
}
