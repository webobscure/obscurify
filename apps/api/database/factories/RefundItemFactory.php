<?php

namespace Database\Factories;

use App\Domain\Financial\Models\Refund;
use App\Domain\Financial\Models\RefundItem;
use App\Domain\Returns\Models\ReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefundItem>
 */
class RefundItemFactory extends Factory
{
    protected $model = RefundItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'refund_id' => Refund::factory(),
            'return_item_id' => ReturnItem::factory(),
            'quantity' => 1,
            'amount' => 1000,
        ];
    }
}
