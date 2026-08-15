<?php

namespace App\Domain\Automation\Http\Resources;

use App\Domain\Automation\Models\WorkflowCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recursive by nature — a group node's `children` renders through this
 * same resource, mirroring M18's SegmentRuleResource.
 *
 * @mixin WorkflowCondition
 */
final class WorkflowConditionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'boolean_operator' => $this->boolean_operator?->value,
            'variable_key' => $this->variable_key,
            'operator' => $this->operator?->value,
            'value' => $this->value,
            'position' => $this->position,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
