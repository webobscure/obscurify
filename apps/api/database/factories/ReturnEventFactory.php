<?php

namespace Database\Factories;

use App\Domain\Returns\Models\ReturnEvent;
use App\Domain\Returns\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnEvent>
 */
class ReturnEventFactory extends Factory
{
    protected $model = ReturnEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_request_id' => ReturnRequest::factory(),
            'type' => 'requested',
            'description' => 'Return requested.',
            'occurred_at' => now(),
        ];
    }
}
