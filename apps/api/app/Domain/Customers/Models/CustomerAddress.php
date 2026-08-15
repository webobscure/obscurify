<?php

namespace App\Domain\Customers\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The customer's current/reusable address book entry. Never read directly
 * by Order rendering — see OrderAddress for why (the customer's address
 * can change after the order is placed).
 *
 * @property string $id
 * @property string $store_id
 * @property string $customer_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $country_code
 * @property string|null $region
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property bool $is_default_billing
 * @property bool $is_default_shipping
 */
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): CustomerAddressFactory
    {
        return CustomerAddressFactory::new();
    }

    protected $fillable = [
        'customer_id',
        'first_name',
        'last_name',
        'phone',
        'country_code',
        'region',
        'city',
        'postal_code',
        'address_line1',
        'address_line2',
        'is_default_billing',
        'is_default_shipping',
    ];

    protected function casts(): array
    {
        return [
            'is_default_billing' => 'boolean',
            'is_default_shipping' => 'boolean',
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
