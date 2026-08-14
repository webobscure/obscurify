<?php

namespace App\Domain\Cms\Http\Resources;

use App\Domain\Cms\Models\PageTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PageTemplate
 */
final class PageTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sections' => $this->sections,
            'created_at' => $this->created_at,
        ];
    }
}
