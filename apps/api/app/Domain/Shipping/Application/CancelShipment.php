<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Shipping\Enums\TrackingEventStatus;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\TrackingEvent;
use App\Domain\Shipping\Support\ShipmentStateMachine;
use App\Domain\Shipping\Support\ShippingProviderRegistry;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * A merchant-initiated, synchronous cancellation (spec section 20's "Cancel
 * shipment" admin action) — deliberately NOT routed through a self-signed
 * webhook the way the fake provider's other test transitions are: this is
 * a real action a merchant takes today, for any provider, not a dev-only
 * simulation of what an external carrier would report.
 */
final class CancelShipment
{
    public function __construct(
        private readonly ShippingProviderRegistry $registry,
        private readonly ShipmentStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Shipment $shipment): Shipment
    {
        return DB::transaction(function () use ($shipment) {
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, ShipmentStatus::Cancelled);

            if ($this->registry->has($locked->provider)) {
                $this->registry->resolve($locked->provider)->cancelShipment($locked);
            }

            $locked->update([
                'status' => ShipmentStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);

            TrackingEvent::query()->create([
                'shipment_id' => $locked->id,
                'status' => TrackingEventStatus::Cancelled->value,
                'description' => 'Shipment cancelled.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('ShipmentCancelled', 'Shipment', $locked->id, [
                'shipment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items', 'trackingEvents']);
        });
    }
}
