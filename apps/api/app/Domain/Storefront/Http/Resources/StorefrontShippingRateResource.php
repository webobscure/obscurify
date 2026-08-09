<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Shipping\Support\ShippingRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately excludes ShippingRate::$metadata wholesale — provider-
 * internal detail (spec section 29: "do not expose provider-internal
 * metadata"; weight/international-surcharge bookkeeping is exactly that).
 * `pickup_points` is the one deliberate, curated exception: customer-
 * facing data the storefront genuinely needs to complete a pickup
 * selection (spec section 5/17), pulled out of metadata explicitly by
 * key rather than passing metadata through.
 *
 * @mixin ShippingRate
 */
final class StorefrontShippingRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider,
            'service_code' => $this->serviceCode,
            'shipping_method_id' => $this->methodId,
            'name' => $this->name,
            'price_amount' => $this->priceAmount,
            'currency' => $this->currency,
            'estimated_days_min' => $this->estimatedDaysMin,
            'estimated_days_max' => $this->estimatedDaysMax,
            'pickup_points' => $this->metadata['pickup_points'] ?? null,
        ];
    }
}
