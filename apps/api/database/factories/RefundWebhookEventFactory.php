<?php

namespace Database\Factories;

use App\Domain\Financial\Models\RefundWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RefundWebhookEvent>
 */
class RefundWebhookEventFactory extends Factory
{
    protected $model = RefundWebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'fake',
            'external_event_id' => (string) Str::ulid(),
            'external_refund_id' => 'fake_refund_'.Str::ulid(),
            'event_type' => 'refund.updated',
            'payload_hash' => hash('sha256', Str::random(32)),
        ];
    }
}
