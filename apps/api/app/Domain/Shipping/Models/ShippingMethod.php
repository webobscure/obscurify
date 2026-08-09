<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Shipping\Enums\ShippingMethodStatus;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $store_id
 * @property string $name
 * @property string $code
 * @property string $provider
 * @property string|null $service_code
 * @property ShippingMethodStatus $status
 * @property int $price_amount
 * @property string $currency
 * @property int|null $estimated_days_min
 * @property int|null $estimated_days_max
 * @property array<string, mixed>|null $settings
 */
class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): ShippingMethodFactory
    {
        return ShippingMethodFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'provider',
        'service_code',
        'status',
        'price_amount',
        'currency',
        'estimated_days_min',
        'estimated_days_max',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShippingMethodStatus::class,
            'price_amount' => 'integer',
            'estimated_days_min' => 'integer',
            'estimated_days_max' => 'integer',
            'settings' => 'array',
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
     * @return BelongsToMany<ShippingZone, $this>
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(ShippingZone::class, 'shipping_method_zones');
    }
}
