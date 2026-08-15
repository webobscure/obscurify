<?php

namespace App\Domain\Customers\Http\Resources;

use App\Domain\Financial\Http\Resources\RefundResource;
use App\Domain\Orders\Models\Order;
use App\Domain\Promotions\Http\Resources\DiscountApplicationResource;
use App\Domain\Returns\Http\Resources\ReturnResource;
use App\Domain\Shipping\Http\Resources\ShipmentResource;
use App\Domain\Storefront\Http\Resources\OrderAddressResource;
use App\Domain\Storefront\Http\Resources\PaymentResource;
use App\Domain\Storefront\Http\Resources\StorefrontShippingLineResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer portal's order view — everything
 * StorefrontOrderConfirmationResource shows plus payment status,
 * shipment tracking, return status, and refund status (spec section 7).
 * Reuses the existing admin ShipmentResource/ReturnResource/RefundResource
 * and the storefront's own PaymentResource/OrderAddressResource as-is
 * rather than duplicating them — none of those expose anything beyond
 * what the order's own owner should see. Items are the one exception:
 * CustomerOrderItemResource (not the storefront's own OrderItemResource)
 * on purpose, since reorder/return both need to reference a specific
 * line by `id`, which the storefront's confirmation-only resource never
 * includes.
 *
 * Callers must eager-load items/shippingAddress/billingAddress/
 * shippingLine/discountApplications/payments/shipments/returns/refunds
 * before rendering (see CustomerOrderController).
 *
 * @mixin Order
 */
final class CustomerOrderResource extends JsonResource
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
            'items' => CustomerOrderItemResource::collection($this->items),
            'discount_applications' => DiscountApplicationResource::collection($this->whenLoaded('discountApplications')),
            'shipping_address' => $this->shippingAddress ? new OrderAddressResource($this->shippingAddress) : null,
            'billing_address' => $this->billingAddress ? new OrderAddressResource($this->billingAddress) : null,
            'shipping_line' => $this->shippingLine ? new StorefrontShippingLineResource($this->shippingLine) : null,
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'shipments' => ShipmentResource::collection($this->whenLoaded('shipments')),
            'returns' => ReturnResource::collection($this->whenLoaded('returns')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'created_at' => $this->created_at,
        ];
    }
}
