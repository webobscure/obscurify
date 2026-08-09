<?php

namespace App\Domain\Shipping\Support;

use Illuminate\Support\Carbon;

/**
 * A provider webhook, already verified and parsed into a provider-neutral
 * shape — mirrors Payments' WebhookEvent. `status` is the provider's own
 * raw vocabulary; mapping it to ShipmentStatus is ProcessShippingWebhook's
 * job, not the provider adapter's, same division of responsibility as
 * Payments.
 */
final readonly class TrackingWebhookEvent
{
    public function __construct(
        public string $eventId,
        public string $externalShipmentId,
        public string $eventType,
        public string $status,
        public ?string $description,
        public ?string $location,
        public Carbon $occurredAt,
    ) {}
}
