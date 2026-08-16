<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Jobs\RequestFiscalizationJob;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Models\OutboxEvent;

/**
 * The 6th ProcessOutboxEventsCommand subscriber (after
 * DispatchWebhooksForEvent M11, DispatchWorkflowTriggersForEvent M19,
 * AnalyticsProjector M20, DispatchNotificationsForEvent M21,
 * SearchIndexingSubscriber M22) — spec section 14/15: "Payment paid →
 * Fiscal receipt requested." Listens for OrderPaymentConfirmed
 * (aggregate_type=Order, aggregate_id=the order's own id — see
 * ProcessPaymentWebhook) and dispatches a queued job rather than
 * fiscalizing inline, the same reasoning as every other subscriber
 * here: a payment webhook response must never wait on a fiscalization
 * provider call.
 */
final class FiscalizationSubscriber
{
    public function handle(OutboxEvent $event, Store $store): void
    {
        if ($event->event_type !== 'OrderPaymentConfirmed') {
            return;
        }

        $paymentId = $event->payload['payment_id'] ?? null;

        RequestFiscalizationJob::dispatch($store->id, $event->aggregate_id, $paymentId);
    }
}
