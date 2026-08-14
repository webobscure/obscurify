<?php

namespace Database\Factories;

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Enums\AppType;
use App\Domain\Apps\Models\App;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<App>
 */
class AppFactory extends Factory
{
    protected $model = App::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => AppType::Private,
            'name' => fake()->unique()->company().' App',
            'slug' => fake()->unique()->slug(),
            'developer' => fake()->company(),
            'description' => fake()->sentence(),
            'redirect_urls' => ['https://example.test/oauth/callback'],
            'requested_scopes' => ['orders.read'],
            'status' => AppStatus::Active,
        ];
    }
}
