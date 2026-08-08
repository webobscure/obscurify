<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Carts\Models\Cart;
use App\Domain\Carts\Support\CartPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The cart's opaque `token` is intentionally never included here — it
 * lives only in the HttpOnly cookie (see StorefrontCartController), never
 * in a response body a script could read.
 *
 * @mixin Cart
 */
final class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totals = CartPricing::for($this->resource);

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'items_subtotal' => $totals['items_subtotal'],
            'total' => $totals['total'],
            'currency' => $totals['currency'],
        ];
    }
}
