<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Shipping\Support\ShippingRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately excludes ShippingRate::$metadata — provider-internal detail
 * (spec section 29: "do not expose provider-internal metadata").
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
        ];
    }
}
