<?php

namespace App\Domain\Returns\Application;

use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Enums\ReturnDisposition as ReturnDispositionValue;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnDisposition;
use App\Domain\Returns\Models\ReturnEvent;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Support\ReturnInventoryContext;
use App\Domain\Returns\Support\ReturnStateMachine;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * The one place a return's disposition actually moves Inventory (spec
 * section 8) — mirrors CompleteFulfillment's role as "the one place stock
 * actually consumes," except in reverse. For each item's already-decided
 * ReturnDisposition (see InspectReturn):
 *
 *   restock       -> InventoryLevel.on_hand += quantity, `ReturnRestocked`
 *                    movement (the one real positive delta this domain
 *                    ever writes).
 *   damaged       -> `ReturnDamaged` movement, zero delta — damaged units
 *                    must never automatically become sellable stock
 *                    (spec section 8), so on_hand is untouched.
 *   discard       -> `ReturnDiscarded` movement, zero delta — the unit is
 *                    destroyed, never having re-entered on_hand.
 *   repair /
 *   manual_review -> no InventoryMovement at all (not in spec section 9's
 *                    list of movement types) — logged only via ReturnEvent,
 *                    left for a human to resolve later through Inventory's
 *                    existing manual-adjustment tool, which this milestone
 *                    does not extend.
 *
 * Every branch still stamps `applied_at` so completion is idempotent-safe
 * to reason about even though the state machine already prevents
 * `inspection -> completed` from running twice.
 */
final class CompleteReturn
{
    public function __construct(
        private readonly ReturnStateMachine $stateMachine,
        private readonly ReturnInventoryContext $inventoryContext,
        private readonly RecordOutboxEvent $recordOutboxEvent,
        private readonly RecomputeCustomerMetrics $recomputeCustomerMetrics,
    ) {}

    public function handle(ReturnRequest $returnRequest): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequest) {
            $locked = ReturnRequest::query()->whereKey($returnRequest->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, ReturnStatus::Completed);

            $items = ReturnItem::query()
                ->where('return_request_id', $locked->id)
                ->with(['orderItem', 'disposition'])
                ->get();

            foreach ($items as $item) {
                $this->applyDisposition($item);
            }

            $locked->update([
                'status' => ReturnStatus::Completed->value,
                'closed_at' => now(),
            ]);

            ReturnEvent::query()->create([
                'return_request_id' => $locked->id,
                'type' => 'completed',
                'description' => 'Return completed.',
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('ReturnCompleted', 'ReturnRequest', $locked->id, [
                'return_request_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            // Resolved via Order rather than trusting
            // ReturnRequest.customer_id directly — that column is
            // nullable (an admin-created return isn't required to set
            // it), whereas Order.customer_id is always set by
            // CompleteCheckout.
            $customerId = Order::query()->find($locked->order_id)?->customer_id;

            if ($customerId !== null) {
                $this->recomputeCustomerMetrics->handle($customerId);
            }

            return $locked->fresh(['items.inspection', 'items.disposition', 'events']);
        });
    }

    private function applyDisposition(ReturnItem $item): void
    {
        $disposition = ReturnDisposition::query()
            ->where('return_item_id', $item->id)
            ->lockForUpdate()
            ->first();

        // No disposition recorded (inspection skipped this item, or the
        // request somehow reached completion without one) — nothing to
        // apply. The state machine already required `inspection` to have
        // run, but not every item is guaranteed a disposition row if a
        // caller inspected a subset; leaving stock untouched is the safe
        // default rather than guessing.
        if ($disposition === null || $disposition->applied_at !== null) {
            return;
        }

        $orderItem = $item->orderItem;
        $context = $orderItem === null ? null : $this->inventoryContext->resolve($orderItem);

        if ($context !== null) {
            match ($disposition->disposition) {
                ReturnDispositionValue::Restock => $this->restock($item, $context['inventoryItem']->id, $context['location']->id),
                ReturnDispositionValue::Damaged => $this->logZeroDeltaMovement($item, $context['inventoryItem']->id, $context['location']->id, InventoryMovementReason::ReturnDamaged),
                ReturnDispositionValue::Discard => $this->logZeroDeltaMovement($item, $context['inventoryItem']->id, $context['location']->id, InventoryMovementReason::ReturnDiscarded),
                ReturnDispositionValue::Repair, ReturnDispositionValue::ManualReview => null,
            };
        }

        $disposition->update(['applied_at' => now()]);
    }

    private function restock(ReturnItem $item, string $inventoryItemId, string $locationId): void
    {
        $level = InventoryLevel::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($level === null) {
            $level = InventoryLevel::query()->create([
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'on_hand' => 0,
                'reserved' => 0,
            ]);
        }

        $level->update(['on_hand' => $level->on_hand + $item->quantity]);

        InventoryMovement::query()->create([
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'quantity_delta' => $item->quantity,
            'reason' => InventoryMovementReason::ReturnRestocked->value,
            'reference_type' => ReturnItem::class,
            'reference_id' => $item->id,
        ]);
    }

    private function logZeroDeltaMovement(ReturnItem $item, string $inventoryItemId, string $locationId, InventoryMovementReason $reason): void
    {
        InventoryMovement::query()->create([
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'quantity_delta' => 0,
            'reason' => $reason->value,
            'reference_type' => ReturnItem::class,
            'reference_id' => $item->id,
        ]);
    }
}
