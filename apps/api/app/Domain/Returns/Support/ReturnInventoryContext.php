<?php

namespace App\Domain\Returns\Support;

use App\Domain\Fulfillment\Models\FulfillmentAllocation;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Locations\Models\Location;
use App\Domain\Orders\Models\OrderItem;

/**
 * Resolves *where* a returned unit should physically land — the same
 * Location its consumed FulfillmentAllocation originally shipped from,
 * so restocked stock returns to the warehouse it actually left (spec
 * section 8/9). Returns null for an untracked/never-fulfilled OrderItem:
 * inventory_movements.inventory_item_id/location_id are both required
 * columns (see AllocateFulfillment's identical untracked-item skip), so
 * there is nothing to write a movement against — ReceiveReturn/
 * CompleteReturn both still record the ReturnRequest/ReturnEvent side of
 * things regardless.
 */
final class ReturnInventoryContext
{
    /**
     * @return array{inventoryItem: InventoryItem, location: Location}|null
     */
    public function resolve(OrderItem $orderItem): ?array
    {
        if ($orderItem->product_variant_id === null) {
            return null;
        }

        $inventoryItem = InventoryItem::query()
            ->where('product_variant_id', $orderItem->product_variant_id)
            ->first();

        if ($inventoryItem === null || ! $inventoryItem->tracked) {
            return null;
        }

        $allocation = FulfillmentAllocation::query()
            ->whereIn('fulfillment_item_id', function ($query) use ($orderItem) {
                $query->select('id')
                    ->from('fulfillment_items')
                    ->where('order_item_id', $orderItem->id);
            })
            ->whereNotNull('consumed_at')
            ->orderByDesc('consumed_at')
            ->first();

        if ($allocation === null) {
            return null;
        }

        $location = Location::query()->find($allocation->location_id);

        if ($location === null) {
            return null;
        }

        return ['inventoryItem' => $inventoryItem, 'location' => $location];
    }
}
