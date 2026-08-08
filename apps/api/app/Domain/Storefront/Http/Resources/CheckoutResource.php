<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Checkouts\Models\Checkout;
use App\Shared\Commerce\Enums\AddressType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately distinct from any admin representation: no store_id,
 * cart_id, or customer_id — nothing the storefront visitor has a reason
 * to see. Callers must eager-load 'addresses' before rendering this
 * (see StorefrontCheckoutController) — the shipping/billing lookup below
 * assumes it's already in memory.
 *
 * @mixin Checkout
 */
final class CheckoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shipping = $this->addresses->firstWhere('type', AddressType::Shipping);
        $billing = $this->addresses->firstWhere('type', AddressType::Billing);

        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'currency' => $this->currency,
            'items_subtotal_amount' => $this->items_subtotal_amount,
            'shipping_amount' => $this->shipping_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'status' => $this->status->value,
            'shipping_address' => $shipping ? new CheckoutAddressResource($shipping) : null,
            'billing_address' => $billing ? new CheckoutAddressResource($billing) : null,
            'expires_at' => $this->expires_at,
        ];
    }
}
