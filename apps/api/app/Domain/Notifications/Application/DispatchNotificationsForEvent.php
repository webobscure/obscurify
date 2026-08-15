<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Automation\Support\WorkflowVariableResolver;
use App\Domain\Notifications\Enums\NotificationTriggerSource;
use App\Domain\Notifications\Models\NotificationEvent;
use App\Domain\Notifications\Support\NotificationDispatchRequest;
use App\Domain\Notifications\Support\NotificationRecipientInput;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Models\OutboxEvent;
use Illuminate\Support\Arr;

/**
 * The Platform Events trigger source (spec section 7) — the 4th
 * ProcessOutboxEventsCommand subscriber, alongside DispatchWebhooksForEvent
 * (M11), DispatchWorkflowTriggersForEvent (M19), and AnalyticsProjector
 * (M20). Matches every enabled NotificationEvent row for this event's
 * `event_type`, in this store, and dispatches one Notification per
 * match via NotificationDispatcher.
 *
 * Reuses WorkflowVariableResolver directly rather than building a
 * second Customer/Order/Payment/Shipment/Return/Store context resolver
 * — the exact same context shape Automation's own variable picker and
 * condition evaluator already use is what spec section 4's template
 * variables ask for.
 */
final class DispatchNotificationsForEvent
{
    public function __construct(
        private readonly WorkflowVariableResolver $variableResolver,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(OutboxEvent $event, Store $store): void
    {
        $matches = NotificationEvent::query()
            ->where('event_type', $event->event_type)
            ->where('is_enabled', true)
            ->with('template')
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        $context = $this->variableResolver->resolve($event, $store);
        $customerId = Arr::get($context, 'customer.id');

        foreach ($matches as $match) {
            if ($match->template === null || ! $match->template->is_active) {
                continue;
            }

            $recipient = is_string($customerId)
                ? NotificationRecipientInput::customer($customerId)
                : NotificationRecipientInput::adHoc(null);

            $this->dispatcher->dispatch($store, new NotificationDispatchRequest(
                channel: $match->channel,
                triggeredBy: NotificationTriggerSource::PlatformEvent,
                recipients: [$recipient],
                context: $context,
                template: $match->template,
                eventType: $event->event_type,
                relatedType: $event->aggregate_type,
                relatedId: $event->aggregate_id,
            ));
        }
    }
}
