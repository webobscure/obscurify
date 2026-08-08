<?php

namespace App\Domain\Media\Http\Resources;

use App\Domain\Media\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Media
 */
final class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'entity_type' => $this->entity_type->value,
            'entity_id' => $this->entity_id,
            'type' => $this->type->value,
            'url' => Storage::disk($this->disk)->url($this->path),
            'alt' => $this->alt,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
