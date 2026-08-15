<?php

namespace App\Domain\CustomerIntelligence\Http\Resources;

use App\Domain\CustomerIntelligence\Models\CustomerTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerTag
 */
final class CustomerTagResource extends JsonResource
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
            'is_system' => $this->is_system,
            'assignment_count' => $this->whenCounted('assignments'),
            'created_at' => $this->created_at,
        ];
    }
}
