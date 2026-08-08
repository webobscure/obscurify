<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryMovement
 */
final class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'inventory_item_id' => $this->inventory_item_id,
            'location_id' => $this->location_id,
            'quantity_delta' => $this->quantity_delta,
            'reason' => $this->reason->value,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
