<?php

namespace App\Domain\Webhooks\Application;

use App\Domain\Webhooks\Enums\WebhookSubscriptionStatus;
use App\Domain\Webhooks\Models\WebhookSubscription;
use Illuminate\Support\Str;

final class CreateWebhookSubscription
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): WebhookSubscription
    {
        return WebhookSubscription::query()->create([
            'owner_type' => 'store',
            'owner_id' => null,
            'name' => $data['name'],
            'target_url' => $data['target_url'],
            'secret' => Str::random(48),
            'event_types' => $data['event_types'],
            'status' => $data['status'] ?? WebhookSubscriptionStatus::Active->value,
        ]);
    }
}
