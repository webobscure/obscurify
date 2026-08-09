<?php

namespace App\Domain\Fulfillment\Application;

use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Fulfillment\Models\FulfillmentAllocation;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use App\Domain\Fulfillment\Support\FulfillmentStateMachine;
use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Cancelling a Fulfillment (spec section 14) releases its allocations —
 * marking them cancelled_at rather than deleting them, so the movement
 * history they drove stays intact — freeing that quantity for a future
 * Fulfillment attempt against the same Order. It does NOT touch the
 * underlying InventoryReservation's status or InventoryLevel.reserved:
 * the reservation itself was never consumed by allocation (see
 * AllocateFulfillment), it stays exactly as reserved as it was before
 * this Fulfillment existed. It also does NOT cancel the Order — a
 * merchant might simply be starting over with a different pick list.
 */
final class CancelFulfillment
{
    public function __construct(
        private readonly FulfillmentStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Fulfillment $fulfillment): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment) {
            $locked = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, FulfillmentStatus::Cancelled);

            $allocations = FulfillmentAllocation::query()
                ->whereIn('fulfillment_item_id', $locked->items()->pluck('id'))
                ->whereNull('consumed_at')
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                $allocation->update(['cancelled_at' => now()]);

                InventoryMovement::query()->create([
                    'inventory_item_id' => $allocation->inventory_item_id,
                    'location_id' => $allocation->location_id,
                    'quantity_delta' => 0,
                    'reason' => InventoryMovementReason::ReservationReleased->value,
                    'reference_type' => FulfillmentAllocation::class,
                    'reference_id' => $allocation->id,
                ]);
            }

            $locked->update(['status' => FulfillmentStatus::Cancelled->value]);

            FulfillmentEvent::query()->create([
                'fulfillment_id' => $locked->id,
                'type' => 'cancelled',
                'description' => 'Fulfillment cancelled.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('FulfillmentCancelled', 'Fulfillment', $locked->id, [
                'fulfillment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items.allocations', 'events']);
        });
    }
}
