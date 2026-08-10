<?php

namespace Database\Factories;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Returns\Enums\ReturnReason;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnItem>
 */
class ReturnItemFactory extends Factory
{
    protected $model = ReturnItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_request_id' => ReturnRequest::factory(),
            'order_item_id' => OrderItem::factory(),
            'quantity' => 1,
            'reason' => ReturnReason::Other,
        ];
    }
}
