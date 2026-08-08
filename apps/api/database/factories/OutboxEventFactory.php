<?php

namespace Database\Factories;

use App\Shared\Commerce\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboxEvent>
 *
 * Deliberately has no `store_id` state: OutboxEvent::creating() always
 * forces it from TenantContext, same as ProductFactory.
 */
class OutboxEventFactory extends Factory
{
    protected $model = OutboxEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_type' => 'OrderCreated',
            'aggregate_type' => 'Order',
            'aggregate_id' => (string) Str::ulid(),
            'payload' => ['order_id' => (string) Str::ulid()],
            'occurred_at' => now(),
        ];
    }
}
