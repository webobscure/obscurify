<?php

namespace App\Domain\Fulfillment\Http\Resources;

use App\Domain\Fulfillment\Models\FulfillmentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FulfillmentItem
 */
final class FulfillmentItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'quantity' => $this->quantity,
            'picked_quantity' => $this->picked_quantity,
            'packed_quantity' => $this->packed_quantity,
            'allocations' => FulfillmentAllocationResource::collection($this->whenLoaded('allocations')),
        ];
    }
}
