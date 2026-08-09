<?php

namespace Database\Factories;

use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingMethodZone;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethodZone>
 */
class ShippingMethodZoneFactory extends Factory
{
    protected $model = ShippingMethodZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipping_method_id' => ShippingMethod::factory(),
            'shipping_zone_id' => ShippingZone::factory(),
        ];
    }
}
