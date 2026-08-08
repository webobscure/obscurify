<?php

namespace App\Domain\Storefront\Http\Resources;

use App\Domain\Media\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Media
 */
final class StorefrontMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => Storage::disk($this->disk)->url($this->path),
            'alt' => $this->alt,
            'position' => $this->position,
        ];
    }
}
