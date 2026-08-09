<?php

namespace App\Domain\Fulfillment\Http\Resources;

use App\Domain\Fulfillment\Models\FulfillmentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FulfillmentAllocation
 */
final class FulfillmentAllocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'inventory_item_id' => $this->inventory_item_id,
            'quantity' => $this->quantity,
            'consumed_at' => $this->consumed_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
        ];
    }
}
