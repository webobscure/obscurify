<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Checkouts\Models\CheckoutAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckoutAddress
 */
final class CheckoutAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'region' => $this->region,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
        ];
    }
}
