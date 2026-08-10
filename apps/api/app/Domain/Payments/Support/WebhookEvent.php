<?php

namespace App\Domain\Payments\Support;

use Illuminate\Support\Carbon;

/**
 * A provider webhook, already verified and parsed into a provider-neutral
 * shape. `status` is the provider's own raw vocabulary (e.g. "succeeded");
 * mapping it to our PaymentStatus/RefundStatus is ProcessPaymentWebhook's/
 * ProcessRefundWebhook's job, not the provider adapter's — keeps each
 * state machine in one place.
 *
 * Shared by both payment and refund events (spec section 7: "Use same
 * webhook pipeline as payments") — exactly one of externalPaymentId/
 * externalRefundId is set, depending on eventType ("payment.updated" vs
 * "refund.updated"); PaymentWebhookController branches on eventType to
 * route to the right processor.
 */
final readonly class WebhookEvent
{
    public function __construct(
        public string $eventId,
        public ?string $externalPaymentId,
        public string $eventType,
        public string $status,
        public int $amount,
        public string $currency,
        public Carbon $occurredAt,
        public ?string $externalRefundId = null,
    ) {}
}
