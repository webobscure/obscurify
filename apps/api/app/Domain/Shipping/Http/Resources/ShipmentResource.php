<?php

namespace App\Domain\Shipping\Http\Resources;

use App\Domain\Shipping\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shipment
 */
final class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'provider' => $this->provider,
            'status' => $this->status->value,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'cancelled_at' => $this->cancelled_at,
            'items' => ShipmentItemResource::collection($this->whenLoaded('items')),
            'tracking_events' => TrackingEventResource::collection($this->whenLoaded('trackingEvents')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
