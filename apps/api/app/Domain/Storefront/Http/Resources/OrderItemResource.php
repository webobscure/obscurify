<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Orders\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Renders the immutable snapshot fields only — never touches the live
 * product()/variant() relations, so a later rename/price change/deletion
 * of the catalog record can never alter a past order's confirmation.
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
