<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Fulfillment\Models\FulfillmentAllocation;
use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
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
 *
 * Since Milestone 7, creating a Shipment also consumes its Fulfillment's
 * allocations (CreateShipment -> CompleteFulfillment) — if this Shipment
 * had already reached `created`, that consumption already happened, so
 * cancelling it must reverse it: on_hand goes back up, recorded as its own
 * ShipmentCancelled movement (never by editing the original
 * FulfillmentCompleted movement or the allocation's consumed_at — history
 * stays intact, the reversal is a new fact). A Shipment cancelled while
 * still `pending`/`ready` (never reached `created`) never triggered
 * consumption, so there's nothing to reverse.
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

            $hadConsumedStock = $locked->status === ShipmentStatus::Created
                || $locked->status === ShipmentStatus::InTransit;

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

            if ($hadConsumedStock) {
                $this->reverseFulfillmentConsumption($locked);
            }

            $this->recordOutboxEvent->handle('ShipmentCancelled', 'Shipment', $locked->id, [
                'shipment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items', 'trackingEvents']);
        });
    }

    private function reverseFulfillmentConsumption(Shipment $shipment): void
    {
        $allocations = FulfillmentAllocation::query()
            ->whereIn('fulfillment_item_id', function ($query) use ($shipment) {
                $query->select('id')->from('fulfillment_items')->where('fulfillment_id', $shipment->fulfillment_id);
            })
            ->whereNotNull('consumed_at')
            ->whereNull('cancelled_at')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            if ($allocation->inventory_reservation_id === null) {
                continue;
            }

            $level = InventoryLevel::query()
                ->where('inventory_item_id', $allocation->inventory_item_id)
                ->where('location_id', $allocation->location_id)
                ->lockForUpdate()
                ->first();

            if ($level === null) {
                continue;
            }

            $level->update(['on_hand' => $level->on_hand + $allocation->quantity]);

            InventoryMovement::query()->create([
                'inventory_item_id' => $allocation->inventory_item_id,
                'location_id' => $allocation->location_id,
                'quantity_delta' => $allocation->quantity,
                'reason' => InventoryMovementReason::ShipmentCancelled->value,
                'reference_type' => Shipment::class,
                'reference_id' => $shipment->id,
            ]);
        }
    }
}
