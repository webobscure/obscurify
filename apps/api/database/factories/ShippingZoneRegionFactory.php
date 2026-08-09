<?php

namespace Database\Factories;

use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Models\ShippingZoneRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZoneRegion>
 */
class ShippingZoneRegionFactory extends Factory
{
    protected $model = ShippingZoneRegion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipping_zone_id' => ShippingZone::factory(),
            'country_code' => 'RU',
            'region' => null,
            'postal_code_pattern' => null,
        ];
    }
}
