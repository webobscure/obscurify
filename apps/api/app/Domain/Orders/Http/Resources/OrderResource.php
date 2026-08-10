<?php

namespace App\Domain\Orders\Http\Resources;

use App\Domain\Fulfillment\Http\Resources\FulfillmentResource;
use App\Domain\Inventory\Http\Resources\InventoryReservationResource;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Http\Resources\ReturnResource;
use App\Domain\Shipping\Http\Resources\ShipmentResource;
use App\Domain\Shipping\Http\Resources\ShippingLineResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing order representation. No payment/refund action affordances
 * belong here (no PaymentGateway exists yet). Fulfillment/Shipment
 * creation happen on their own pages now (Milestone 7) — this resource
 * exposes reservations/fulfillments/shipments read-only, per spec section
 * 16's "Order page should display: Reservations, Fulfillments, Shipments."
 *
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'checkout_id' => $this->checkout_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'currency' => $this->currency,
            'items_subtotal_amount' => $this->items_subtotal_amount,
            'shipping_amount' => $this->shipping_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'order_status' => $this->order_status->value,
            'financial_status' => $this->financial_status->value,
            'fulfillment_status' => $this->fulfillment_status->value,
            'customer' => new OrderCustomerResource($this->whenLoaded('customer')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'shipping_address' => $this->whenLoaded('shippingAddress', fn () => $this->shippingAddress ? new OrderAddressResource($this->shippingAddress) : null),
            'billing_address' => $this->whenLoaded('billingAddress', fn () => $this->billingAddress ? new OrderAddressResource($this->billingAddress) : null),
            'shipping_line' => $this->whenLoaded('shippingLine', fn () => $this->shippingLine ? new ShippingLineResource($this->shippingLine) : null),
            'reservations' => InventoryReservationResource::collection($this->whenLoaded('reservations')),
            'fulfillments' => FulfillmentResource::collection($this->whenLoaded('fulfillments')),
            'shipments' => ShipmentResource::collection($this->whenLoaded('shipments')),
            'returns' => ReturnResource::collection($this->whenLoaded('returns')),
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
