<?php

namespace Database\Factories;

use App\Domain\Themes\Enums\ThemeStatus;
use App\Domain\Themes\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Theme>
 */
class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' theme',
            'slug' => fake()->unique()->slug(),
            'status' => ThemeStatus::Draft,
        ];
    }
}
