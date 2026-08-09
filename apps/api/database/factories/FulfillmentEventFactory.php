<?php

namespace Database\Factories;

use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentEvent>
 */
class FulfillmentEventFactory extends Factory
{
    protected $model = FulfillmentEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fulfillment_id' => Fulfillment::factory(),
            'type' => 'created',
            'description' => 'Fulfillment created.',
            'occurred_at' => now(),
        ];
    }
}
