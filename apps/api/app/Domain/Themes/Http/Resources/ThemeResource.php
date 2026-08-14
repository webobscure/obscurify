<?php

namespace App\Domain\Themes\Http\Resources;

use App\Domain\Themes\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Theme
 */
final class ThemeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'is_active' => $this->relationLoaded('activePointer') && $this->activePointer !== null,
            'versions' => ThemeVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
