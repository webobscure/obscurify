<?php

namespace App\Domain\Apps\Http\Resources\Gateway;

use App\Domain\Shipping\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShippingMethod
 */
final class GatewayShippingMethodResource extends JsonResource
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
        ];
    }
}
