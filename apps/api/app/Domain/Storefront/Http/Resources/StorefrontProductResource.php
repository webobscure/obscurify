<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately distinct from the admin ProductResource: no store_id, no
 * internal status/audit fields — just what a storefront needs to render
 * a listing card or a product page.
 *
 * @mixin Product
 *
 * @property-read int|null $min_variant_price
 */
final class StorefrontProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'vendor' => $this->vendor,
            'product_type' => $this->product_type,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
            ],
            'price' => $this->min_variant_price === null ? null : [
                'amount' => $this->min_variant_price,
                'currency' => $this->store->default_currency,
            ],
            'variants' => StorefrontProductVariantResource::collection($this->whenLoaded('variants')),
            'media' => StorefrontMediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
