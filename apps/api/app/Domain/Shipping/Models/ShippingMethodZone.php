<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ShippingMethodZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit pivot model, not a bare Eloquent sync() table — see the
 * migration's docblock. Mirrors CollectionProduct's shape exactly.
 *
 * @property int $id
 * @property string $store_id
 * @property string $shipping_method_id
 * @property string $shipping_zone_id
 */
class ShippingMethodZone extends Model
{
    /** @use HasFactory<ShippingMethodZoneFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ShippingMethodZoneFactory
    {
        return ShippingMethodZoneFactory::new();
    }

    protected $fillable = [
        'shipping_method_id',
        'shipping_zone_id',
    ];

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
