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
        ];
    }
}
