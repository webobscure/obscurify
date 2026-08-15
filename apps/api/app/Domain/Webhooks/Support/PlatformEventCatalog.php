<?php

namespace App\Domain\Webhooks\Support;

/**
 * A documentation-and-admin-UI catalog of every `event_type` string this
 * platform actually records via RecordOutboxEvent — not an enforced
 * enum. OutboxEvent.event_type stays a free-form string (30+ call sites
 * across every domain already write to it; retrofitting all of them
 * onto a shared enum is out of scope for this milestone and adds no
 * dispatch-time behavior, since WebhookSubscription matching is a plain
 * string/`"*"` comparison either way — see DispatchWebhooksForEvent).
 * This list exists so the admin "subscribe to these events" UI has
 * something real to offer instead of a freehand text field, and so this
 * file is the one place to update when a new domain starts recording
 * events.
 */
final class PlatformEventCatalog
{
    /**
     * @return string[]
     */
    public static function knownEventTypes(): array
    {
        return [
            'OrderCreated',
            'OrderFinancialStatusChanged',
            'PaymentCreated',
            'PaymentPaid',
            'PaymentFailed',
            'PaymentCancelled',
            'OrderPaymentConfirmed',
            'RefundRequested',
            'RefundProcessing',
            'RefundCompleted',
            'RefundFailed',
            'RefundCancelled',
            'FulfillmentCreated',
            'InventoryAllocated',
            'PickingStarted',
            'PackingCompleted',
            'FulfillmentCompleted',
            'FulfillmentCancelled',
            'ReservationConsumed',
            'ShipmentCreated',
            'ShipmentCancelled',
            'ReturnRequested',
            'ReturnApproved',
            'ReturnRejected',
            'ReturnReceived',
            'ReturnInspected',
            'ReturnCompleted',
            'ReturnCancelled',
            'PromotionApplied',
            'PromotionRemoved',
            'DiscountCodeRedeemed',
            // Milestone 16 (Customer Accounts) — never added to this
            // catalog when that milestone shipped; added here as part of
            // Milestone 18's own catalog additions below.
            'CustomerCreated',
            'CustomerUpdated',
            'CustomerLoggedIn',
            'CustomerVerified',
            'CustomerAddressUpdated',
            'CustomerOrderViewed',
            'CustomerReturnRequested',
            // Milestone 18 (Customer Intelligence) — see
            // docs/architecture/customer-intelligence.md section 6.
            'CustomerTagAssigned',
            'CustomerTagRemoved',
            'CustomerEnteredSegment',
            'CustomerLeftSegment',
            'CustomerBecameVip',
            'CustomerBecameInactive',
            // Milestone 19 (Automation Engine) — see
            // docs/architecture/automation.md §3. ProductBackInStock and
            // InventoryBelowThreshold are recorded for real by
            // AdjustInventory; AppWebhookReceived is recorded for real by
            // the apps/v1 automation events gateway
            // (AutomationEventGatewayController). CustomerEnteredSegment/
            // CustomerLeftSegment/CustomerBecameVip/CustomerBecameInactive
            // (above) and OrderCreated/RefundCompleted/ReturnApproved/
            // ReturnCompleted/CustomerCreated/CustomerUpdated (already in
            // this catalog) cover the rest of spec section 3's trigger
            // examples; OrderPaymentConfirmed is this platform's actual
            // name for the spec's "OrderPaid" example, and PaymentPaid is
            // its "PaymentSucceeded" example — both already catalogued
            // above, no new event type needed. OrderCancelled is
            // catalog-only: no order-cancellation feature exists yet in
            // this codebase to emit it (see docs/adr/025-automation-engine.md).
            'ProductBackInStock',
            'InventoryBelowThreshold',
            'AppWebhookReceived',
            'OrderCancelled',
            // Shipment status-transition events (Milestone 8, Shipping) —
            // ProcessShippingWebhook has recorded these for real since
            // that milestone shipped, but (like Milestone 16's customer
            // events) they were never added to this catalog until
            // Milestone 20 needed ShipmentDelivered as an analytics
            // aggregation source.
            'ShipmentAccepted',
            'ShipmentInTransit',
            'ShipmentOutForDelivery',
            'ShipmentDelivered',
            'ShipmentDeliveryException',
            'ShipmentFailed',
            // Milestone 20 (Analytics Platform) — see
            // docs/architecture/analytics.md §8.
            'InventoryChanged',
            'WorkflowExecuted',
            'WorkflowExecutionFailed',
            // Milestone 22 (Search & Discovery Platform) — see
            // docs/architecture/search.md §4. All fired with
            // aggregate_type=Product, aggregate_id=the product's id
            // (even PriceChanged/VariantUpdated, which technically
            // originate on a variant) — the search index operates at
            // product granularity, so every one of these reduces to
            // "reindex this product," and standardizing the aggregate
            // keeps SearchIndexingSubscriber trivial (see ADR-028).
            // CollectionUpdated/CategoryUpdated are the one exception:
            // aggregate_type=Collection/Category, since facet labels for
            // those are resolved live rather than reindexed per product.
            'ProductCreated',
            'ProductUpdated',
            'ProductDeleted',
            'VariantUpdated',
            'PriceChanged',
            'VisibilityChanged',
            'CollectionUpdated',
            'CategoryUpdated',
            // Search analytics (spec section 12: "Integrate with
            // Analytics Platform") — see docs/architecture/analytics.md
            // §8 and AnalyticsProjector.
            'SearchPerformed',
            'SearchResultClicked',
        ];
    }
}
