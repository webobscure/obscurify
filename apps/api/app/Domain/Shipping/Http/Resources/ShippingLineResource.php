<?php

namespace App\Domain\Shipping\Http\Resources;

use App\Domain\Orders\Models\OrderShippingLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately `name`, not the underlying column's `title` — matches
 * StorefrontShippingLineResource's shape exactly, since both render the
 * same shared `ShippingLine` concept (see that TypeScript interface's
 * docblock).
 *
 * @mixin OrderShippingLine
 */
final class ShippingLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider,
            'service_code' => $this->service_code,
            'name' => $this->title,
            'price_amount' => $this->price_amount,
            'currency' => $this->currency,
            'estimated_days_min' => $this->estimated_days_min,
            'estimated_days_max' => $this->estimated_days_max,
        ];
    }
}
