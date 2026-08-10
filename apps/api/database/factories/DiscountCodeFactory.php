<?php

namespace Database\Factories;

use App\Domain\Promotions\Enums\DiscountCodeStatus;
use App\Domain\Promotions\Enums\PromotionTriggerType;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountCode>
 */
class DiscountCodeFactory extends Factory
{
    protected $model = DiscountCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory()->state(['trigger_type' => PromotionTriggerType::Code]),
            'code' => strtoupper(fake()->unique()->bothify('SAVE####')),
            'status' => DiscountCodeStatus::Active,
        ];
    }
}
