<?php

namespace App\Domain\Shipping\Http\Resources;

use App\Domain\Shipping\Models\ShippingZoneRegion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShippingZoneRegion
 */
final class ShippingZoneRegionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_code' => $this->country_code,
            'region' => $this->region,
            'postal_code_pattern' => $this->postal_code_pattern,
        ];
    }
}
