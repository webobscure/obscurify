<?php

namespace App\Domain\Orders\Http\Resources;

use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Http\Resources\ShipmentResource;
use App\Domain\Shipping\Http\Resources\ShippingLineResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing order representation — no payment/refund/fulfillment
 * action affordances belong here beyond shipment creation, this milestone
 * deliberately ships read-only order visibility otherwise (no
 * PaymentGateway actions, no fulfillment module yet — spec section 27).
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
            'shipments' => ShipmentResource::collection($this->whenLoaded('shipments')),
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
