<?php

namespace App\Domain\Customers\Models;

use App\Domain\Customers\Enums\CustomerIdentityType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CustomerIdentityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The actual login credential — one row per way a Customer can prove who
 * they are. Only `email_password` exists today (see
 * CustomerIdentityType), kept as its own table rather than columns on
 * Customer so a future identity type never requires a schema change.
 * `identifier` is the normalized (lowercased/trimmed) email; uniqueness
 * is enforced at (store_id, type, identifier), which is what actually
 * stops duplicate registrations — Customer.email itself stays
 * intentionally non-unique (see Customer's docblock).
 *
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property CustomerIdentityType $type
 * @property string $identifier
 * @property string $secret_hash
 * @property int $failed_attempts
 * @property Carbon|null $locked_until
 */
class CustomerIdentity extends Model
{
    /** @use HasFactory<CustomerIdentityFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): CustomerIdentityFactory
    {
        return CustomerIdentityFactory::new();
    }

    protected $fillable = [
        'customer_id',
        'type',
        'identifier',
        'secret_hash',
        'failed_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerIdentityType::class,
            'failed_attempts' => 'integer',
            'locked_until' => 'datetime',
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

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
