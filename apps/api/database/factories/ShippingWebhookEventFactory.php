<?php

namespace Database\Factories;

use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Models\ShippingWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShippingWebhookEvent>
 */
class ShippingWebhookEventFactory extends Factory
{
    protected $model = ShippingWebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => FakeShippingProvider::CODE,
            'external_event_id' => (string) Str::ulid(),
            'external_shipment_id' => 'fake_ship_'.(string) Str::ulid(),
            'event_type' => 'shipment.updated',
            'payload_hash' => hash('sha256', (string) Str::random()),
        ];
    }
}
