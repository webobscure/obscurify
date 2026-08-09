<?php

namespace Database\Factories;

use App\Domain\Shipping\Enums\ShippingZoneStatus;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    protected $model = ShippingZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Zone',
            'status' => ShippingZoneStatus::Active,
        ];
    }
}
