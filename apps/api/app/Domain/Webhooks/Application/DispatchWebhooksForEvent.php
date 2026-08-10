<?php

namespace App\Domain\Webhooks\Application;

use App\Domain\Webhooks\Enums\WebhookDeliveryStatus;
use App\Domain\Webhooks\Enums\WebhookSubscriptionStatus;
use App\Domain\Webhooks\Jobs\DeliverWebhookJob;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Domain\Webhooks\Models\WebhookSubscription;
use App\Shared\Commerce\Models\OutboxEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Fans an OutboxEvent out to every active, matching WebhookSubscription
 * in the event's own store — called from ProcessOutboxEventsCommand,
 * inside the same tenant scope/transaction that claims the OutboxEvent
 * row (see that command for why this stays poll-based rather than
 * hooking RecordOutboxEvent directly: ~30 existing call sites across
 * every domain already call it, and none of them need to know webhooks
 * exist).
 *
 * Idempotent by construction: WebhookDelivery has a unique constraint on
 * (webhook_subscription_id, outbox_event_id), so a concurrent or retried
 * dispatch for the same event/subscription pair claims the same row
 * instead of creating a duplicate (mirrors ProcessShippingWebhook's
 * insert-then-catch-UniqueConstraintViolationException pattern for
 * inbound webhooks, applied to outbound fan-out).
 */
final class DispatchWebhooksForEvent
{
    public function handle(OutboxEvent $event): int
    {
        $subscriptions = WebhookSubscription::query()
            ->where('status', WebhookSubscriptionStatus::Active->value)
            ->get()
            ->filter(fn (WebhookSubscription $subscription) => $subscription->subscribesTo($event->event_type));

        $dispatched = 0;

        foreach ($subscriptions as $subscription) {
            $delivery = $this->claimDelivery($subscription, $event);

            if ($delivery !== null) {
                DeliverWebhookJob::dispatch($delivery->id);
                $dispatched++;
            }
        }

        return $dispatched;
    }

    private function claimDelivery(WebhookSubscription $subscription, OutboxEvent $event): ?WebhookDelivery
    {
        try {
            return DB::transaction(fn () => WebhookDelivery::query()->create([
                'webhook_subscription_id' => $subscription->id,
                'outbox_event_id' => $event->id,
                'event_type' => $event->event_type,
                'status' => WebhookDeliveryStatus::Pending->value,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Already claimed by a concurrent/earlier dispatch for this
            // exact (subscription, event) pair — not an error, just a
            // no-op re-delivery guard.
            return null;
        }
    }
}
