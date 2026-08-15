<?php

namespace App\Domain\Customers\Http\Resources;

use App\Domain\Orders\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unlike the storefront's own OrderItemResource (which never needs to
 * reference a specific line by id), the customer portal does: "buy
 * again"'s skipped-line report and return-request submission both key
 * off order_item_id, so this includes `id` and `product_variant_id`
 * alongside the same immutable snapshot fields.
 *
 * @mixin OrderItem
 */
final class CustomerOrderItemResource extends JsonResource
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
