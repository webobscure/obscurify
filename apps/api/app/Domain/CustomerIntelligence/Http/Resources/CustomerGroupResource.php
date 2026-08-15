<?php

namespace App\Domain\CustomerIntelligence\Http\Resources;

use App\Domain\CustomerIntelligence\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerGroup
 */
final class CustomerGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'member_count' => $this->whenCounted('computedMemberships'),
            'rules' => SegmentRuleResource::collection($this->whenLoaded('rootRules')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
