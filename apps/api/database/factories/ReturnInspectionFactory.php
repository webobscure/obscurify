<?php

namespace Database\Factories;

use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Models\ReturnInspection;
use App\Domain\Returns\Models\ReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnInspection>
 */
class ReturnInspectionFactory extends Factory
{
    protected $model = ReturnInspection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_item_id' => ReturnItem::factory(),
            'condition' => ReturnCondition::New,
            'inspected_at' => now(),
        ];
    }
}
