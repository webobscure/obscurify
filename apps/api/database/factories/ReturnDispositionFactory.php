<?php

namespace Database\Factories;

use App\Domain\Returns\Enums\ReturnDisposition as ReturnDispositionValue;
use App\Domain\Returns\Models\ReturnDisposition;
use App\Domain\Returns\Models\ReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnDisposition>
 */
class ReturnDispositionFactory extends Factory
{
    protected $model = ReturnDisposition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_item_id' => ReturnItem::factory(),
            'disposition' => ReturnDispositionValue::Restock,
            'decided_at' => now(),
        ];
    }
}
