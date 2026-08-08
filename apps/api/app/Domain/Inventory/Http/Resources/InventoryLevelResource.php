<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\InventoryLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryLevel
 */
final class InventoryLevelResource extends JsonResource
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
            'on_hand' => $this->on_hand,
            'reserved' => $this->reserved,
            'available' => $this->available(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
