<?php

namespace App\Domain\Analytics\Support;

use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Models\ReturnRequest;
use App\Shared\Commerce\Models\OutboxEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Analytics' only consumer of the Platform Event Bus (spec section 2) —
 * called from ProcessOutboxEventsCommand, inside the same tenant-scoped
 * transaction that claims each OutboxEvent, alongside
 * DispatchWebhooksForEvent (M11) and DispatchWorkflowTriggersForEvent
 * (M19). For the handful of event types listed below, normalizes the
 * event into an AnalyticsEvent row.
 *
 * For Order-rooted events (`OrderCreated`, `OrderPaymentConfirmed`) the
 * projector reads the `Order` aggregate exactly once here — its outbox
 * payload alone doesn't carry `customer_id` or the line-item/discount/
 * shipping breakdown Top Products/Categories/Collections/Discounts/
 * Shipping Methods need. This is the ONE place in the whole Analytics
 * domain that ever touches a commerce table; every downstream consumer
 * (AnalyticsAggregator, AnalyticsSnapshotBuilder, every widget/report
 * read) only ever reads `analytics_events`/`analytics_snapshots`
 * afterward — see docs/adr/026-analytics-platform.md.
 *
 * Idempotent via the unique constraint on `analytics_events.outbox_event_id`
 * — the same claim-or-skip pattern WebhookDelivery/WorkflowExecution use
 * for their own fan-out from the same outbox stream.
 */
final class AnalyticsProjector
{
    /**
     * @var string[]
     */
    private const array RELEVANT_EVENT_TYPES = [
        'OrderCreated',
        'OrderPaymentConfirmed',
        'RefundCompleted',
        'ReturnCompleted',
        'ShipmentDelivered',
        'CustomerCreated',
        'PromotionApplied',
        'InventoryChanged',
        'WorkflowExecuted',
        'WorkflowExecutionFailed',
        'SearchPerformed',
        'SearchResultClicked',
    ];

    public function __construct(private readonly AnalyticsAggregator $aggregator) {}

    public function project(OutboxEvent $event): void
    {
        if (! in_array($event->event_type, self::RELEVANT_EVENT_TYPES, true)) {
            return;
        }

        match ($event->event_type) {
            'OrderCreated' => $this->projectOrderCreated($event),
            'OrderPaymentConfirmed' => $this->projectOrderPaymentConfirmed($event),
            'ReturnCompleted' => $this->projectReturnCompleted($event),
            'CustomerCreated' => $this->claim($event, customerId: $event->aggregate_id),
            'RefundCompleted' => $this->claim($event, amount: $this->intPayload($event, 'amount')),
            'PromotionApplied' => $this->claim($event, amount: $this->intPayload($event, 'discount_amount')),
            'SearchPerformed' => $this->claim($event, payload: ['result_count' => $this->intPayload($event, 'result_count') ?? 0]),
            'SearchResultClicked' => $this->claim($event, payload: ['product_id' => $event->payload['product_id'] ?? null]),
            default => $this->claim($event),
        };
    }

    private function projectOrderCreated(OutboxEvent $event): void
    {
        $order = Order::withoutGlobalScopes()->find($event->aggregate_id);

        if ($order === null) {
            $this->claim($event);

            return;
        }

        $isFirstOrder = ! AnalyticsEvent::withoutGlobalScopes()
            ->where('store_id', $event->store_id)
            ->where('event_type', 'OrderCreated')
            ->where('customer_id', $order->customer_id)
            ->exists();

        $this->claim($event, customerId: $order->customer_id, amount: $order->total_amount, currency: $order->currency, payload: [
            'is_first_order' => $isFirstOrder,
            'number' => $order->number,
            'email' => $order->email,
            'order_status' => $order->order_status->value,
            'financial_status' => $order->financial_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
        ]);
    }

    private function projectOrderPaymentConfirmed(OutboxEvent $event): void
    {
        $order = Order::withoutGlobalScopes()
            ->with(['items.product.categories', 'items.product.collections', 'discountApplications', 'shippingLine'])
            ->find($event->aggregate_id);

        if ($order === null) {
            $this->claim($event);

            return;
        }

        $lineItems = $order->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'product_title' => $item->product_title,
            'category_ids' => $item->product?->categories->pluck('id')->all() ?? [],
            'collection_ids' => $item->product?->collections->pluck('id')->all() ?? [],
            'quantity' => $item->quantity,
            'amount' => $item->line_total_amount,
        ])->all();

        $discounts = $order->discountApplications->map(fn ($application) => [
            'promotion_id' => $application->promotion_id,
            'discount_code_id' => $application->discount_code_id,
            'label' => $application->promotion_name ?? $application->code,
            'amount' => $application->amount,
        ])->all();

        $shipping = $order->shippingLine !== null ? [
            'label' => $order->shippingLine->title,
            'amount' => $order->shippingLine->price_amount,
        ] : null;

        $this->claim($event, customerId: $order->customer_id, amount: $order->total_amount, currency: $order->currency, payload: [
            'number' => $order->number,
            'email' => $order->email,
            'line_items' => $lineItems,
            'discounts' => $discounts,
            'shipping' => $shipping,
        ]);
    }

    private function projectReturnCompleted(OutboxEvent $event): void
    {
        $return = ReturnRequest::withoutGlobalScopes()->find($event->aggregate_id);
        $order = $return !== null ? Order::withoutGlobalScopes()->find($return->order_id) : null;

        $this->claim($event, customerId: $order?->customer_id, payload: [
            'number' => $return?->number,
            'order_id' => $return?->order_id,
            'status' => $return?->status->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function claim(OutboxEvent $event, ?string $customerId = null, ?int $amount = null, ?string $currency = null, array $payload = []): void
    {
        try {
            $analyticsEvent = DB::transaction(fn () => AnalyticsEvent::query()->create([
                'outbox_event_id' => $event->id,
                'event_type' => $event->event_type,
                'occurred_at' => $event->occurred_at ?? now(),
                'aggregate_type' => $event->aggregate_type,
                'aggregate_id' => $event->aggregate_id,
                'customer_id' => $customerId,
                'amount' => $amount,
                'currency' => $currency,
                'payload' => $payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Already projected by a concurrent/earlier pass over this
            // exact outbox event — not an error, just a no-op re-run guard.
            return;
        }

        // "Real-time" insight (spec section 1) means re-aggregating the
        // affected day immediately rather than waiting for a scheduled
        // batch job — cheap, since it only re-scans that one day's
        // (typically small) set of AnalyticsEvent rows, never the whole
        // history. See docs/architecture/analytics.md §8.
        $this->aggregator->aggregateDay($event->store_id, $analyticsEvent->occurred_at);
    }

    private function intPayload(OutboxEvent $event, string $key): ?int
    {
        $value = $event->payload[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
