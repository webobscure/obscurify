<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The storefront-safe order representation. Deliberately no store_id,
 * customer_id, or checkout_id. The Order's own ULID doubles as the
 * confirmation token (spec section 38) — it has ~80 bits of randomness,
 * unlike the tenant-sequential `number`, so a visitor can never guess
 * their way into another customer's order by incrementing it.
 *
 * Callers must eager-load items/shippingAddress/billingAddress before
 * rendering (see StorefrontCheckoutController / StorefrontOrderController).
 *
 * @mixin Order
 */
final class OrderConfirmationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
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
            'items' => OrderItemResource::collection($this->items),
            'shipping_address' => $this->shippingAddress ? new OrderAddressResource($this->shippingAddress) : null,
            'billing_address' => $this->billingAddress ? new OrderAddressResource($this->billingAddress) : null,
            'created_at' => $this->created_at,
        ];
    }
}
