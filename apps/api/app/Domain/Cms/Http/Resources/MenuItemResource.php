<?php

namespace App\Domain\Cms\Http\Resources;

use App\Domain\Cms\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
final class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'parent_id' => $this->parent_id,
            'label' => $this->label,
            'target_type' => $this->target_type->value,
            'target_id' => $this->target_id,
            'url' => $this->url,
            'position' => $this->position,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
