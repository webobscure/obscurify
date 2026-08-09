<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Shipping\Enums\ShippingZoneStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ShippingZoneFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property ShippingZoneStatus $status
 */
class ShippingZone extends Model
{
    /** @use HasFactory<ShippingZoneFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): ShippingZoneFactory
    {
        return ShippingZoneFactory::new();
    }

    protected $fillable = [
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShippingZoneStatus::class,
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
     * @return HasMany<ShippingZoneRegion, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(ShippingZoneRegion::class);
    }

    /**
     * @return BelongsToMany<ShippingMethod, $this>
     */
    public function shippingMethods(): BelongsToMany
    {
        return $this->belongsToMany(ShippingMethod::class, 'shipping_method_zones');
    }
}
