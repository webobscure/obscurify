<?php

namespace App\Domain\Catalog\Http\Resources;

use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
final class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'product_id' => $this->product_id,
            'title' => $this->title,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price_amount' => $this->price_amount,
            'compare_at_price_amount' => $this->compare_at_price_amount,
            'cost_amount' => $this->cost_amount,
            'currency' => $this->currency,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'status' => $this->status->value,
            'option_values' => ProductOptionValueResource::collection($this->whenLoaded('optionValues')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
