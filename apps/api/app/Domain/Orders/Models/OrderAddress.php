<?php

namespace App\Domain\Orders\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Enums\AddressType;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\OrderAddressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot row — never updated after Order creation, and never
 * re-derived from the live CustomerAddress (which the customer may edit
 * or delete after the order is placed).
 *
 * @property string $id
 * @property string $store_id
 * @property string $order_id
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
class OrderAddress extends Model
{
    /** @use HasFactory<OrderAddressFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): OrderAddressFactory
    {
        return OrderAddressFactory::new();
    }

    protected $fillable = [
        'order_id',
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
