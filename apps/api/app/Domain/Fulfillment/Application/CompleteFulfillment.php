<?php

namespace App\Domain\Fulfillment\Application;

use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Fulfillment\Models\FulfillmentAllocation;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use App\Domain\Fulfillment\Support\FulfillmentStateMachine;
use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Orders\Enums\FulfillmentStatus as OrderFulfillmentStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * The one place a reservation actually becomes consumed (spec section 7's
 * invariant): called by Shipping\Application\CreateShipment, inside the
 * same transaction, immediately after a Shipment is successfully created
 * against this Fulfillment — the point stock has genuinely left the
 * building, per docs/architecture/shipping.md §10's own recommendation
 * for where this milestone should put that transition. Never called
 * directly from an HTTP controller: completion is a side effect of
 * shipping, not an independent admin action (spec section 15 has no bare
 * "complete" trigger other than the one that also creates the shipment).
 *
 * Not decreasing on_hand at checkout or on payment (spec section 7) is
 * enforced by omission: no other code path in this codebase touches
 * InventoryLevel.on_hand for a reservation — AdjustInventory is the only
 * other writer, and it's a merchant-initiated correction, not part of the
 * order lifecycle.
 */
final class CompleteFulfillment
{
    public function __construct(
        private readonly FulfillmentStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Fulfillment $fulfillment): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment) {
            $locked = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, FulfillmentStatus::Completed);

            $allocations = FulfillmentAllocation::query()
                ->whereIn('fulfillment_item_id', $locked->items()->pluck('id'))
                ->whereNull('consumed_at')
                ->whereNull('cancelled_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                $this->consumeAllocation($allocation);
            }

            $locked->update([
                'status' => FulfillmentStatus::Completed->value,
                'completed_at' => now(),
            ]);

            FulfillmentEvent::query()->create([
                'fulfillment_id' => $locked->id,
                'type' => 'completed',
                'description' => 'Fulfillment completed — stock consumed.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('FulfillmentCompleted', 'Fulfillment', $locked->id, [
                'fulfillment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            $this->recordOutboxEvent->handle('ReservationConsumed', 'Fulfillment', $locked->id, [
                'fulfillment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
                'allocation_count' => $allocations->count(),
            ]);

            $this->refreshOrderFulfillmentStatus($locked);

            return $locked->fresh(['items.allocations', 'events']);
        });
    }

    private function consumeAllocation(FulfillmentAllocation $allocation): void
    {
        if ($allocation->inventory_reservation_id === null) {
            // Untracked item — nothing was ever reserved or on-hand for it
            // (see AllocateFulfillment), so there is nothing to consume.
            $allocation->update(['consumed_at' => now()]);

            return;
        }

        $level = InventoryLevel::query()
            ->where('inventory_item_id', $allocation->inventory_item_id)
            ->where('location_id', $allocation->location_id)
            ->lockForUpdate()
            ->firstOrFail();

        $level->update([
            'on_hand' => max(0, $level->on_hand - $allocation->quantity),
            'reserved' => max(0, $level->reserved - $allocation->quantity),
        ]);

        InventoryMovement::query()->create([
            'inventory_item_id' => $allocation->inventory_item_id,
            'location_id' => $allocation->location_id,
            'quantity_delta' => -$allocation->quantity,
            'reason' => InventoryMovementReason::FulfillmentCompleted->value,
            'reference_type' => FulfillmentAllocation::class,
            'reference_id' => $allocation->id,
        ]);

        $allocation->update(['consumed_at' => now()]);

        $reservation = InventoryReservation::query()
            ->whereKey($allocation->inventory_reservation_id)
            ->lockForUpdate()
            ->first();

        if ($reservation === null || $reservation->status !== ReservationStatus::Active) {
            return;
        }

        $consumedForReservation = (int) FulfillmentAllocation::query()
            ->where('inventory_reservation_id', $reservation->id)
            ->whereNotNull('consumed_at')
            ->sum('quantity');

        // A reservation may be split across multiple Fulfillments (partial
        // fulfillment) — only flip it to Consumed once every unit of it
        // has actually been consumed, not on the first Fulfillment to
        // touch it.
        if ($consumedForReservation >= $reservation->quantity) {
            $reservation->update([
                'status' => ReservationStatus::Consumed->value,
                'consumed_at' => now(),
            ]);
        }
    }

    /**
     * Rolls the Order's own 3-state fulfillment_status rollup
     * (Unfulfilled/Partial/Fulfilled — App\Domain\Orders\Enums\
     * FulfillmentStatus, distinct from this domain's own detailed status)
     * forward based on how much of the Order is now completed-fulfilled
     * versus ordered. Deliberately recomputed from scratch rather than
     * incremented, so it's always correct even if fulfillments complete
     * out of order or a future correction changes quantities.
     */
    private function refreshOrderFulfillmentStatus(Fulfillment $fulfillment): void
    {
        $order = Order::query()->whereKey($fulfillment->order_id)->lockForUpdate()->firstOrFail();

        $orderedTotal = (int) OrderItem::query()->where('order_id', $order->id)->sum('quantity');

        $fulfilledTotal = (int) DB::table('fulfillment_items')
            ->join('fulfillments', 'fulfillments.id', '=', 'fulfillment_items.fulfillment_id')
            ->where('fulfillments.order_id', $order->id)
            ->where('fulfillments.status', FulfillmentStatus::Completed->value)
            ->sum('fulfillment_items.quantity');

        $status = match (true) {
            $orderedTotal > 0 && $fulfilledTotal >= $orderedTotal => OrderFulfillmentStatus::Fulfilled,
            $fulfilledTotal > 0 => OrderFulfillmentStatus::Partial,
            default => OrderFulfillmentStatus::Unfulfilled,
        };

        $order->update(['fulfillment_status' => $status->value]);
    }
}
