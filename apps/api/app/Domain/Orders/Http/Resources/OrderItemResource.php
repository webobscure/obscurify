<?php

namespace App\Domain\Orders\Http\Resources;

use App\Domain\Orders\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Renders the immutable snapshot fields — product_id/product_variant_id
 * are exposed here (unlike the storefront resource) purely as historical
 * traceability links for merchants; every other field always reflects
 * what was true at order time, never the live Product/Variant.
 *
 * @mixin OrderItem
 */
final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_title' => $this->product_title,
            'variant_title' => $this->variant_title,
            'sku' => $this->sku,
            'unit_price_amount' => $this->unit_price_amount,
            'quantity' => $this->quantity,
            'line_total_amount' => $this->line_total_amount,
            'currency' => $this->currency,
        ];
    }
}
