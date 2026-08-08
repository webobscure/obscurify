<?php

namespace App\Domain\Checkouts\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Enums\AddressType;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CheckoutAddressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $store_id
 * @property string $checkout_id
 * @property AddressType $type
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $country_code
 * @property string|null $region
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $address_line1
 * @property string|null $address_line2
 */
class CheckoutAddress extends Model
{
    /** @use HasFactory<CheckoutAddressFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): CheckoutAddressFactory
    {
        return CheckoutAddressFactory::new();
    }

    protected $fillable = [
        'checkout_id',
        'type',
        'first_name',
        'last_name',
        'phone',
        'country_code',
        'region',
        'city',
        'postal_code',
        'address_line1',
        'address_line2',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
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
     * @return BelongsTo<Checkout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }
}
