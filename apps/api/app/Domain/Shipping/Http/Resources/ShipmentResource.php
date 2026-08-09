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
            'fulfillment_id' => $this->fulfillment_id,
            'provider' => $this->provider,
            'status' => $this->status->value,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'cancelled_at' => $this->cancelled_at,
            'items' => ShipmentItemResource::collection($this->whenLoaded('items')),
            'tracking_events' => TrackingEventResource::collection($this->whenLoaded('trackingEvents')),
            // Destination-or-pickup-point (spec section 22): 'destination'
            // is the shipping address for courier/express services;
            // 'pickup_point' is populated instead when the order snapshot
            // (OrderShippingLine.metadata) recorded one for Pickup.
            'destination' => $this->when(
                $this->relationLoaded('order') && $this->order?->relationLoaded('shippingAddress'),
                fn () => $this->order?->shippingAddress === null ? null : [
                    'city' => $this->order->shippingAddress->city,
                    'country_code' => $this->order->shippingAddress->country_code,
                    'postal_code' => $this->order->shippingAddress->postal_code,
                ],
            ),
            'pickup_point' => $this->when(
                $this->relationLoaded('order') && $this->order?->relationLoaded('shippingLine'),
                fn () => $this->order?->shippingLine?->metadata['pickup_point'] ?? null,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
