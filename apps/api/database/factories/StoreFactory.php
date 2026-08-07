<?php

namespace Database\Factories;

use App\Domain\Stores\Enums\StoreStatus;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 999999),
            'status' => StoreStatus::Active,
            'default_currency' => 'RUB',
            'default_locale' => 'ru',
            'timezone' => 'Europe/Moscow',
            'settings' => null,
        ];
    }
}
