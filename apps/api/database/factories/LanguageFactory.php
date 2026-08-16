<?php

namespace Database\Factories;

use App\Domain\Localization\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->languageCode();

        return [
            'code' => $code,
            'name' => fake()->word(),
            'native_name' => fake()->word(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
