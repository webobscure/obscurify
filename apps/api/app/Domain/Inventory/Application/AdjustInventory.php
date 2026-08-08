<?php

namespace App\Domain\Inventory\Application;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Locations\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdjustInventory
{
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

            $newOnHand = $level->on_hand + $data['quantity_delta'];

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

            return $level;
        });
    }
}
