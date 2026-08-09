<?php

namespace App\Domain\Fulfillment\Http\Resources;

use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Shipping\Http\Resources\ShipmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Fulfillment
 */
final class FulfillmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'completed_at' => $this->completed_at,
            'items' => FulfillmentItemResource::collection($this->whenLoaded('items')),
            'events' => FulfillmentEventResource::collection($this->whenLoaded('events')),
            'shipments' => ShipmentResource::collection($this->whenLoaded('shipments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
