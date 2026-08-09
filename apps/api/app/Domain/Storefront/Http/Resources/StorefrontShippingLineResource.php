<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Orders\Models\OrderShippingLine;
use App\Domain\Shipping\Models\ShippingQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Renders either a selected-but-not-yet-completed ShippingQuote (on
 * CheckoutResource) or a completed order's OrderShippingLine snapshot (on
 * OrderConfirmationResource) — same shape either way, since a quote is
 * exactly what an OrderShippingLine snapshots.
 *
 * @mixin ShippingQuote|OrderShippingLine
 */
final class StorefrontShippingLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider,
            'service_code' => $this->service_code,
            'name' => $this->resource instanceof OrderShippingLine ? $this->resource->title : $this->resource->name,
            'price_amount' => $this->price_amount,
            'currency' => $this->currency,
            'estimated_days_min' => $this->estimated_days_min,
            'estimated_days_max' => $this->estimated_days_max,
        ];
    }
}
