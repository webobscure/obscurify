<?php

namespace App\Domain\Webhooks\Application;

use App\Domain\Webhooks\Enums\WebhookDeliveryStatus;
use App\Domain\Webhooks\Jobs\DeliverWebhookJob;
use App\Domain\Webhooks\Models\WebhookDelivery;
use Illuminate\Validation\ValidationException;

/**
 * Manual re-delivery for a `failed`/`exhausted` WebhookDelivery — resets
 * it back to `pending` and re-dispatches the same job, so a merchant
 * fixing a broken endpoint doesn't have to wait for the original
 * backoff schedule (or, past `exhausted`, has no other way to retry at
 * all).
 */
final class RetryWebhookDelivery
{
    public function handle(WebhookDelivery $delivery): WebhookDelivery
    {
        if (! in_array($delivery->status, [WebhookDeliveryStatus::Failed, WebhookDeliveryStatus::Exhausted], true)) {
            throw ValidationException::withMessages([
                'delivery' => 'Only a failed or exhausted delivery can be retried.',
            ]);
        }

        $delivery->update(['status' => WebhookDeliveryStatus::Pending->value]);

        DeliverWebhookJob::dispatch($delivery->id);

        // On the sync queue driver (tests), the job above has already
        // run and mutated this row through its own model instance —
        // re-fetch so the response reflects what actually happened
        // rather than the "pending" snapshot from two lines up.
        return $delivery->fresh();
    }
}
