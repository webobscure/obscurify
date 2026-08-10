<?php

namespace Database\Factories;

use App\Domain\Promotions\Enums\PromotionStackingMode;
use App\Domain\Promotions\Enums\PromotionStatus;
use App\Domain\Promotions\Enums\PromotionTriggerType;
use App\Domain\Promotions\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' promotion',
            'trigger_type' => PromotionTriggerType::Automatic,
            'stacking_mode' => PromotionStackingMode::Stackable,
            'priority' => 0,
            'status' => PromotionStatus::Active,
        ];
    }
}
