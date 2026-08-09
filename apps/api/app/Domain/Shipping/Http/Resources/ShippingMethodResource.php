<?php

namespace App\Domain\Shipping\Http\Resources;

use App\Domain\Shipping\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShippingMethod
 */
final class ShippingMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'provider' => $this->provider,
            'service_code' => $this->service_code,
            'status' => $this->status->value,
            'price_amount' => $this->price_amount,
            'currency' => $this->currency,
            'estimated_days_min' => $this->estimated_days_min,
            'estimated_days_max' => $this->estimated_days_max,
            'settings' => $this->settings,
            'zone_ids' => $this->whenLoaded('zones', fn () => $this->zones->pluck('id')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
