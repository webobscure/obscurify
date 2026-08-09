<?php

namespace App\Domain\Fulfillment\Application;

use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use App\Domain\Fulfillment\Exceptions\FulfillmentOvershipmentException;
use App\Domain\Fulfillment\Exceptions\InvalidFulfillmentTransitionException;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Fulfillment\Models\FulfillmentAllocation;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use App\Domain\Fulfillment\Models\FulfillmentItem;
use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * The Allocation Engine (spec section 6): decides exactly which
 * InventoryReservation rows (and therefore which Locations) back each
 * FulfillmentItem, splitting deterministically the same way
 * ReserveInventory already does (ordered by location id, oldest-created
 * reservation first) — this is consumption bookkeeping layered on top of
 * checkout's existing reservations, not a new reservation mechanism.
 * Never touches InventoryLevel.on_hand or .reserved — allocation is a
 * claim on stock that is already reserved, not a new stock movement (see
 * CompleteFulfillment for where physical consumption actually happens).
 */
final class AllocateFulfillment
{
    public function __construct(
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Fulfillment $fulfillment): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment) {
            $locked = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();

            // Deliberately stricter than the state machine's generic
            // same-state-is-a-no-op rule (which fits webhook-driven
            // transitions like Shipment/Payment's): allocation has a real,
            // non-idempotent side effect — re-running it against an
            // already-allocated Fulfillment would try to claim the same
            // reservation capacity twice and fail confusingly deep inside
            // allocateItem(). Reject it cleanly here instead.
            if ($locked->status !== FulfillmentStatus::Pending) {
                throw InvalidFulfillmentTransitionException::make($locked->status, FulfillmentStatus::Allocated);
            }

            $items = $locked->items()->with('orderItem')->get();

            foreach ($items as $item) {
                $this->allocateItem($locked, $item);
            }

            $locked->update(['status' => FulfillmentStatus::Allocated->value]);

            FulfillmentEvent::query()->create([
                'fulfillment_id' => $locked->id,
                'type' => 'allocated',
                'description' => 'Inventory allocated.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('InventoryAllocated', 'Fulfillment', $locked->id, [
                'fulfillment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items.allocations', 'events']);
        });
    }

    private function allocateItem(Fulfillment $fulfillment, FulfillmentItem $item): void
    {
        $variantId = $item->orderItem->product_variant_id;

        $inventoryItem = $variantId === null
            ? null
            : InventoryItem::query()->where('product_variant_id', $variantId)->first();

        // Untracked or non-stock items (no InventoryItem, or tracked=false)
        // have nothing to allocate against — the same rule ReserveInventory
        // applies at checkout time. Nothing to lock, nothing to record.
        if ($inventoryItem === null || ! $inventoryItem->tracked) {
            return;
        }

        $reservations = InventoryReservation::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('order_id', $fulfillment->order_id)
            ->where('status', ReservationStatus::Active->value)
            ->orderBy('location_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $item->quantity;

        foreach ($reservations as $reservation) {
            if ($remaining <= 0) {
                break;
            }

            $alreadyAllocated = (int) FulfillmentAllocation::query()
                ->where('inventory_reservation_id', $reservation->id)
                ->whereNull('cancelled_at')
                ->sum('quantity');

            $available = $reservation->quantity - $alreadyAllocated;

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);

            $allocation = FulfillmentAllocation::query()->create([
                'fulfillment_item_id' => $item->id,
                'location_id' => $reservation->location_id,
                'inventory_item_id' => $inventoryItem->id,
                'inventory_reservation_id' => $reservation->id,
                'quantity' => $take,
            ]);

            InventoryMovement::query()->create([
                'inventory_item_id' => $inventoryItem->id,
                'location_id' => $reservation->location_id,
                'quantity_delta' => 0,
                'reason' => InventoryMovementReason::FulfillmentAllocated->value,
                'reference_type' => FulfillmentAllocation::class,
                'reference_id' => $allocation->id,
            ]);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw FulfillmentOvershipmentException::exceedsReservedQuantity($item->order_item_id);
        }
    }
}
