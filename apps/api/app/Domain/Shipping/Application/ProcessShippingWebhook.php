<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Shipping\Enums\TrackingEventStatus;
use App\Domain\Shipping\Exceptions\MalformedShippingWebhookPayloadException;
use App\Domain\Shipping\Exceptions\ShippingWebhookReplayException;
use App\Domain\Shipping\Exceptions\UnknownShipmentException;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShippingWebhookEvent;
use App\Domain\Shipping\Models\TrackingEvent;
use App\Domain\Shipping\Support\ShipmentStateMachine;
use App\Domain\Shipping\Support\TrackingWebhookEvent;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The provider-neutral webhook handler — mirrors ProcessPaymentWebhook's
 * shape exactly, including the tenant-resolution and idempotency
 * reasoning documented there. See ShippingWebhookController for where
 * verifyWebhook()/parseWebhook() are called before this.
 */
final class ProcessShippingWebhook
{
    private const MAX_POLL_ATTEMPTS = 100;

    private const POLL_INTERVAL_MICROSECONDS = 20_000;

    public function __construct(
        private readonly ShipmentStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(string $providerCode, TrackingWebhookEvent $event, string $rawPayload): void
    {
        $tolerance = (int) config('commerce.shipping.webhook.replay_tolerance_seconds');

        if ($event->occurredAt->lt(now()->subSeconds($tolerance))) {
            throw ShippingWebhookReplayException::make();
        }

        $payloadHash = hash('sha256', $rawPayload);

        $claimed = $this->claimOrAlreadyProcessed($providerCode, $event, $payloadHash);

        if (! $claimed) {
            // Already processed by another (possibly concurrent) delivery
            // of the exact same event — idempotent no-op, nothing to do
            // (spec section 23).
            return;
        }

        $shipment = Shipment::withoutGlobalScopes()
            ->where('provider', $providerCode)
            ->where('external_shipment_id', $event->externalShipmentId)
            ->first();

        if ($shipment === null) {
            ShippingWebhookEvent::query()
                ->where('provider', $providerCode)
                ->where('external_event_id', $event->eventId)
                ->update(['processed_at' => now()]);

            throw UnknownShipmentException::forExternalId($providerCode, $event->externalShipmentId);
        }

        $store = Store::query()->findOrFail($shipment->store_id);

        app(TenantContext::class)->scope($store, function () use ($providerCode, $event, $shipment, $store) {
            DB::transaction(function () use ($providerCode, $event, $shipment, $store) {
                $lockedShipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

                $target = $this->mapEventToStatus($event);

                if ($target !== $lockedShipment->status) {
                    if (! $this->stateMachine->canTransition($lockedShipment->status, $target)) {
                        // Out-of-order or redundant delivery (spec section
                        // 25/37) — e.g. a "delivered" webhook arriving
                        // after the shipment was already cancelled. The
                        // tracking event below still records that we saw
                        // it; the Shipment's own status is left alone
                        // rather than erroring, same policy as Payments.
                        Log::warning('shipping.webhook.invalid_transition', [
                            'shipment_id' => $lockedShipment->id,
                            'from' => $lockedShipment->status->value,
                            'to' => $target->value,
                        ]);
                    } else {
                        $this->applyTransition($lockedShipment, $target);
                    }
                }

                TrackingEvent::query()->create([
                    'shipment_id' => $lockedShipment->id,
                    'status' => $this->mapEventToTrackingStatus($event),
                    'description' => $event->description,
                    'occurred_at' => $event->occurredAt,
                    'location' => $event->location,
                ]);

                ShippingWebhookEvent::query()
                    ->where('provider', $providerCode)
                    ->where('external_event_id', $event->eventId)
                    ->update(['store_id' => $store->id, 'processed_at' => now()]);
            });
        });
    }

    private function mapEventToStatus(TrackingWebhookEvent $event): ShipmentStatus
    {
        return match ($event->status) {
            'accepted' => ShipmentStatus::Accepted,
            'in_transit' => ShipmentStatus::InTransit,
            'out_for_delivery' => ShipmentStatus::OutForDelivery,
            'delivered' => ShipmentStatus::Delivered,
            'delivery_exception' => ShipmentStatus::DeliveryException,
            'failed' => ShipmentStatus::Failed,
            'cancelled' => ShipmentStatus::Cancelled,
            default => throw MalformedShippingWebhookPayloadException::make("unrecognized status \"{$event->status}\"."),
        };
    }

    private function mapEventToTrackingStatus(TrackingWebhookEvent $event): string
    {
        return match ($event->status) {
            'accepted' => TrackingEventStatus::Accepted->value,
            'in_transit' => TrackingEventStatus::InTransit->value,
            'out_for_delivery' => TrackingEventStatus::OutForDelivery->value,
            'delivered' => TrackingEventStatus::Delivered->value,
            'delivery_exception' => TrackingEventStatus::DeliveryException->value,
            'failed' => TrackingEventStatus::Failed->value,
            'cancelled' => TrackingEventStatus::Cancelled->value,
            default => throw MalformedShippingWebhookPayloadException::make("unrecognized status \"{$event->status}\"."),
        };
    }

    private function applyTransition(Shipment $shipment, ShipmentStatus $target): void
    {
        $updates = ['status' => $target->value];

        // First of accepted/in_transit to actually arrive sets shipped_at
        // — a real carrier may skip straight to in_transit without ever
        // reporting accepted, so this can't be pinned to one status alone.
        if (in_array($target, [ShipmentStatus::Accepted, ShipmentStatus::InTransit], true) && $shipment->shipped_at === null) {
            $updates['shipped_at'] = now();
        }

        if ($target === ShipmentStatus::Delivered) {
            $updates['delivered_at'] = now();
        }

        if ($target === ShipmentStatus::Cancelled) {
            $updates['cancelled_at'] = now();
        }

        $shipment->update($updates);

        $eventType = match ($target) {
            ShipmentStatus::Accepted => 'ShipmentAccepted',
            ShipmentStatus::InTransit => 'ShipmentInTransit',
            ShipmentStatus::OutForDelivery => 'ShipmentOutForDelivery',
            ShipmentStatus::Delivered => 'ShipmentDelivered',
            ShipmentStatus::DeliveryException => 'ShipmentDeliveryException',
            ShipmentStatus::Failed => 'ShipmentFailed',
            ShipmentStatus::Cancelled => 'ShipmentCancelled',
            default => throw new RuntimeException("No outbox event mapped for shipment status \"{$target->value}\"."),
        };

        $this->recordOutboxEvent->handle($eventType, 'Shipment', $shipment->id, [
            'shipment_id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'store_id' => $shipment->store_id,
            'status' => $target->value,
        ]);
    }

    /**
     * @return bool true if this call holds the claim and must process the
     *              event; false if another delivery already fully
     *              processed it (idempotent no-op).
     */
    private function claimOrAlreadyProcessed(string $provider, TrackingWebhookEvent $event, string $payloadHash): bool
    {
        for ($attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS; $attempt++) {
            try {
                DB::transaction(function () use ($provider, $event, $payloadHash) {
                    ShippingWebhookEvent::query()->create([
                        'provider' => $provider,
                        'external_event_id' => $event->eventId,
                        'external_shipment_id' => $event->externalShipmentId,
                        'event_type' => $event->eventType,
                        'payload_hash' => $payloadHash,
                    ]);
                });

                return true;
            } catch (UniqueConstraintViolationException) {
                $existing = ShippingWebhookEvent::query()
                    ->where('provider', $provider)
                    ->where('external_event_id', $event->eventId)
                    ->first();

                if ($existing === null) {
                    // The other holder rolled back / the row is gone —
                    // the slot is free again, retry the claim.
                    continue;
                }

                if ($existing->payload_hash !== $payloadHash) {
                    throw MalformedShippingWebhookPayloadException::make('event id reused with a different payload.');
                }

                if ($existing->processed_at !== null) {
                    return false;
                }

                // Still in flight elsewhere — poll rather than block, same
                // reasoning as ProcessPaymentWebhook.
                usleep(self::POLL_INTERVAL_MICROSECONDS);
            }
        }

        throw new RuntimeException('Could not claim webhook event after '.self::MAX_POLL_ATTEMPTS.' attempts.');
    }
}
