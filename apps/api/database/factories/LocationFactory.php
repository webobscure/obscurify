<?php

namespace Database\Factories;

use App\Domain\Locations\Enums\LocationStatus;
use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 *
 * Deliberately has no `store_id` state: Location::creating() always forces
 * it from TenantContext, same as ProductFactory.
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Warehouse',
            'status' => LocationStatus::Active,
            'country' => 'RU',
            'region' => fake()->state(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
        ];
    }
}
