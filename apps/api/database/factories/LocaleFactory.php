<?php

namespace Database\Factories;

use App\Domain\Localization\Models\Language;
use App\Domain\Localization\Models\Locale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Locale>
 */
class LocaleFactory extends Factory
{
    protected $model = Locale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->languageCode();

        return [
            'code' => $code,
            'language_code' => Language::factory()->create(['code' => $code])->code,
            'fallback_locale_code' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
