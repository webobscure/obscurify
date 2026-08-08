<?php

namespace Database\Factories;

use App\Domain\Payments\Models\PaymentWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentWebhookEvent>
 *
 * Unlike other factories in this codebase, `store_id` genuinely has no
 * default here — PaymentWebhookEvent does not use BelongsToTenant, so
 * nothing auto-injects it. Tests that need a resolved event must pass
 * `store_id` explicitly.
 */
class PaymentWebhookEventFactory extends Factory
{
    protected $model = PaymentWebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'fake',
            'external_event_id' => (string) fake()->unique()->uuid(),
            'event_type' => 'payment.updated',
            'payload_hash' => hash('sha256', fake()->uuid()),
        ];
    }
}
