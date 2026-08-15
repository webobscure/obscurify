<?php

namespace App\Domain\Customers\Http\Resources;

use App\Domain\Customers\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress
 */
final class CustomerAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'region' => $this->region,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'is_default_billing' => $this->is_default_billing,
            'is_default_shipping' => $this->is_default_shipping,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
