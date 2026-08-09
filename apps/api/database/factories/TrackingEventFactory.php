<?php

namespace Database\Factories;

use App\Domain\Shipping\Enums\TrackingEventStatus;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingEvent>
 */
class TrackingEventFactory extends Factory
{
    protected $model = TrackingEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'status' => TrackingEventStatus::Created,
            'description' => 'Shipment created.',
            'occurred_at' => now(),
            'location' => null,
            'metadata' => null,
        ];
    }
}
