<?php

namespace App\Domain\Themes\Http\Resources;

use App\Domain\Themes\Models\ThemeVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThemeVersion
 */
final class ThemeVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'theme_id' => $this->theme_id,
            'created_from_version_id' => $this->created_from_version_id,
            'version_number' => $this->version_number,
            'status' => $this->status->value,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
        ];
    }
}
