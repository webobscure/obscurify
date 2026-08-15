<?php

namespace App\Domain\Inventory\Application;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Locations\Models\Location;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdjustInventory
{
    public function __construct(private readonly RecordOutboxEvent $recordOutboxEvent) {}

    /**
     * Transactionally adjusts on-hand stock for one InventoryItem at one
     * Location and leaves an immutable InventoryMovement record.
     *
     * $item and $location are both resolved under the same active
     * TenantContext (route binding / tenant-scoped lookup), so in practice
     * they can never belong to different stores — the explicit check below
     * is a defense-in-depth invariant, directly exercised by
     * AdjustInventoryTest with models constructed to bypass that guarantee.
     *
     * Also fires the Automation Engine's two inventory triggers (spec
     * section 3) when on-hand stock actually *crosses* a threshold, not
     * on every adjustment: 0 -> positive is ProductBackInStock; positive
     * -> at-or-below `low_stock_threshold` (an opt-in, nullable per-item
     * value) is InventoryBelowThreshold. See
     * docs/architecture/automation.md §3 for why these two are wired for
     * real while a few other spec-listed trigger examples are catalog-
     * only in this milestone.
     *
     * @param  array{location_id: string, quantity_delta: int, reason: string, reference_type?: string|null, reference_id?: string|null}  $data
     */
    public function handle(InventoryItem $item, Location $location, array $data, ?string $createdBy = null): InventoryLevel
    {
        if ($item->store_id !== $location->store_id) {
            throw ValidationException::withMessages([
                'location_id' => 'The inventory item and location must belong to the same store.',
            ]);
        }

        return DB::transaction(function () use ($item, $location, $data, $createdBy) {
            $level = InventoryLevel::query()
                ->where('inventory_item_id', $item->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if ($level === null) {
                $level = InventoryLevel::query()->create([
                    'inventory_item_id' => $item->id,
                    'location_id' => $location->id,
                    'on_hand' => 0,
                    'reserved' => 0,
                ]);
            }

            $previousOnHand = $level->on_hand;
            $newOnHand = $previousOnHand + $data['quantity_delta'];

            if ($newOnHand < 0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => 'Adjustment would result in negative on-hand stock.',
                ]);
            }

            $level->update(['on_hand' => $newOnHand]);

            InventoryMovement::query()->create([
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'quantity_delta' => $data['quantity_delta'],
                'reason' => $data['reason'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_by' => $createdBy,
            ]);

            // General-purpose "something moved" event (Milestone 20,
            // Analytics) — fired on every adjustment, unlike the two
            // threshold-crossing events below which only fire when stock
            // crosses a specific boundary.
            $this->recordOutboxEvent->handle('InventoryChanged', 'InventoryItem', $item->id, [
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'store_id' => $item->store_id,
                'quantity_delta' => $data['quantity_delta'],
                'on_hand' => $newOnHand,
                'reason' => $data['reason'],
            ]);

            $this->recordThresholdCrossingEvents($item, $location, $previousOnHand, $newOnHand);

            return $level;
        });
    }

    private function recordThresholdCrossingEvents(InventoryItem $item, Location $location, int $previousOnHand, int $newOnHand): void
    {
        if ($previousOnHand <= 0 && $newOnHand > 0) {
            $this->recordOutboxEvent->handle('ProductBackInStock', 'InventoryItem', $item->id, [
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'store_id' => $item->store_id,
                'on_hand' => $newOnHand,
            ]);
        }

        $threshold = $item->low_stock_threshold;

        if ($threshold !== null && $previousOnHand > $threshold && $newOnHand <= $threshold) {
            $this->recordOutboxEvent->handle('InventoryBelowThreshold', 'InventoryItem', $item->id, [
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'store_id' => $item->store_id,
                'on_hand' => $newOnHand,
                'threshold' => $threshold,
            ]);
        }
    }
}
