<?php

namespace App\Domain\Webhooks\Application;

use App\Domain\Webhooks\Models\WebhookSubscription;

final class UpdateWebhookSubscription
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(WebhookSubscription $subscription, array $data): WebhookSubscription
    {
        $subscription->update($data);

        return $subscription;
    }
}
