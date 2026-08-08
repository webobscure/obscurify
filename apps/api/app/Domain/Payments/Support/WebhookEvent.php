<?php

namespace App\Domain\Payments\Support;

use Illuminate\Support\Carbon;

/**
 * A provider webhook, already verified and parsed into a provider-neutral
 * shape. `status` is the provider's own raw vocabulary (e.g. "succeeded");
 * mapping it to our PaymentStatus is ProcessPaymentWebhook's job, not the
 * provider adapter's — keeps the state machine in one place.
 */
final readonly class WebhookEvent
{
    public function __construct(
        public string $eventId,
        public string $externalPaymentId,
        public string $eventType,
        public string $status,
        public int $amount,
        public string $currency,
        public Carbon $occurredAt,
    ) {}
}
