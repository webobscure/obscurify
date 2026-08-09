<?php

namespace App\Domain\Shipping\Http\Resources;

use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShippingZone
 */
final class ShippingZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'regions' => ShippingZoneRegionResource::collection($this->whenLoaded('regions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
