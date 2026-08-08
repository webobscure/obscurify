<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Support\VariantAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately distinct from the admin ProductVariantResource: no
 * cost_amount, no store_id, no status, no timestamps — nothing the
 * storefront/customer has a reason to see.
 *
 * @mixin ProductVariant
 */
final class StorefrontProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availability = VariantAvailability::for($this->resource);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'sku' => $this->sku,
            'price' => [
                'amount' => $this->price_amount,
                'currency' => $this->currency,
            ],
            'compare_at_price' => $this->compare_at_price_amount === null ? null : [
                'amount' => $this->compare_at_price_amount,
                'currency' => $this->currency,
            ],
            'options' => $this->whenLoaded(
                'optionValues',
                fn () => $this->optionValues->map(fn ($value) => [
                    'option' => $value->option->name,
                    'value' => $value->value,
                ])->values(),
            ),
            'availability' => [
                'tracked' => $availability->tracked,
                'available' => $availability->available,
                'in_stock' => $availability->inStock,
            ],
            'media' => StorefrontMediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
