<?php

namespace Database\Factories;

use App\Domain\Webhooks\Enums\WebhookSubscriptionStatus;
use App\Domain\Webhooks\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => 'store',
            'owner_id' => null,
            'name' => fake()->unique()->words(2, true).' subscription',
            'target_url' => 'https://example.test/webhooks/'.Str::random(8),
            'secret' => Str::random(40),
            'event_types' => ['OrderCreated'],
            'status' => WebhookSubscriptionStatus::Active,
        ];
    }
}
