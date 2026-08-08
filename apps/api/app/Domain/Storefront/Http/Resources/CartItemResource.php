<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Carts\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
final class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->product_variant_id,
            'title' => $this->variant->title,
            'sku' => $this->variant->sku,
            'quantity' => $this->quantity,
            'price' => [
                'amount' => $this->variant->price_amount,
                'currency' => $this->variant->currency,
            ],
            'line_total' => [
                'amount' => $this->quantity * $this->variant->price_amount,
                'currency' => $this->variant->currency,
            ],
            'media' => StorefrontMediaResource::collection(
                $this->variant->media->isNotEmpty() ? $this->variant->media : $this->variant->product->media,
            ),
        ];
    }
}
