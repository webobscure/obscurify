<?php

namespace App\Domain\Builder\Http\Resources;

use App\Domain\Themes\Models\ThemeAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThemeAsset
 */
final class ThemeAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'theme_version_id' => $this->theme_version_id,
            'type' => $this->type->value,
            'url' => $this->url(),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'created_at' => $this->created_at,
        ];
    }
}
