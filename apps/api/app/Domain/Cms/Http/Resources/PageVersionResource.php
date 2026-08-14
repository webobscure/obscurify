<?php

namespace App\Domain\Cms\Http\Resources;

use App\Domain\Cms\Models\PageVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PageVersion
 */
final class PageVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->page_id,
            'created_from_version_id' => $this->created_from_version_id,
            'version_number' => $this->version_number,
            'status' => $this->status->value,
            'published_at' => $this->published_at,
            'sections' => $this->sections,
            'created_at' => $this->created_at,
        ];
    }
}
