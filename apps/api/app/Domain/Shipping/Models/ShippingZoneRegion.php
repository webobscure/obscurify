<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ShippingZoneRegionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One destination-matching rule for a ShippingZone (spec section 3) — see
 * MatchesShippingDestination for how country_code/region/postal_code_
 * pattern combine to match an address.
 *
 * @property string $id
 * @property string $store_id
 * @property string $shipping_zone_id
 * @property string $country_code
 * @property string|null $region
 * @property string|null $postal_code_pattern
 */
class ShippingZoneRegion extends Model
{
    /** @use HasFactory<ShippingZoneRegionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): ShippingZoneRegionFactory
    {
        return ShippingZoneRegionFactory::new();
    }

    protected $fillable = [
        'shipping_zone_id',
        'country_code',
        'region',
        'postal_code_pattern',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
