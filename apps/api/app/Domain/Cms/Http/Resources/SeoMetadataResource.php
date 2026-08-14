<?php

namespace App\Domain\Cms\Http\Resources;

use App\Domain\Cms\Models\SeoMetadata;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeoMetadata
 */
final class SeoMetadataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'og_image' => $this->og_image,
        ];
    }
}
