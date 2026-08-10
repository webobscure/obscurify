<?php

namespace App\Domain\Returns\Application;

use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnEvent;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Support\ReturnInventoryContext;
use App\Domain\Returns\Support\ReturnStateMachine;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * The package has physically arrived at the warehouse. Writes a
 * zero-delta `ReturnReceived` InventoryMovement per item purely as an
 * audit trail entry (spec section 9 lists it as a movement type) — it
 * never touches `on_hand`/`reserved`, since spec section 8 is explicit
 * that inventory changes only happen after inspection. That's the exact
 * zero-delta bookkeeping pattern `FulfillmentAllocated`/
 * `ReservationReleased` already established for "something happened, but
 * no physical stock moved yet."
 */
final class ReceiveReturn
{
    public function __construct(
        private readonly ReturnStateMachine $stateMachine,
        private readonly ReturnInventoryContext $inventoryContext,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(ReturnRequest $returnRequest): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest) {
            $locked = ReturnRequest::query()->whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, ReturnStatus::Received);

            $items = ReturnItem::query()->where('return_request_id', $locked->id)->with('orderItem')->get();

            foreach ($items as $item) {
                $orderItem = $item->orderItem;

                if ($orderItem === null) {
                    continue;
                }

                $context = $this->inventoryContext->resolve($orderItem);

                if ($context === null) {
                    continue;
                }

                InventoryMovement::query()->create([
                    'inventory_item_id' => $context['inventoryItem']->id,
                    'location_id' => $context['location']->id,
                    'quantity_delta' => 0,
                    'reason' => InventoryMovementReason::ReturnReceived->value,
                    'reference_type' => ReturnItem::class,
                    'reference_id' => $item->id,
                ]);
            }

            $locked->update([
                'status' => ReturnStatus::Received->value,
                'received_at' => now(),
            ]);

            ReturnEvent::query()->create([
                'return_request_id' => $locked->id,
                'type' => 'received',
                'description' => 'Package received.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('ReturnReceived', 'ReturnRequest', $locked->id, [
                'return_request_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items', 'events']);
        });
    }
}
