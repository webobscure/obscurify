<?php

namespace App\Domain\Catalog\Http\Resources;

use App\Domain\Catalog\Models\Product;
use App\Domain\Media\Http\Resources\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'vendor' => $this->vendor,
            'product_type' => $this->product_type,
            'tags' => $this->tags ?? [],
            'status' => $this->status->value,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'options' => ProductOptionResource::collection($this->whenLoaded('options')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'collection_ids' => $this->whenLoaded('collections', fn () => $this->collections->pluck('id')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
