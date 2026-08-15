<?php

namespace App\Domain\CustomerIntelligence\Http\Resources;

use App\Domain\CustomerIntelligence\Models\SegmentRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recursive by nature — a group node's `children` renders through this
 * same resource. Callers must eager-load `children` (and each child's
 * own `children`, ...) as deep as the tree actually goes; a node whose
 * children weren't loaded simply renders an empty array via `whenLoaded`,
 * which for a genuine group node with real children would be a bug, not
 * a valid "no children" state — see SegmentController/CustomerGroupController.
 *
 * @mixin SegmentRule
 */
final class SegmentRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'boolean_operator' => $this->boolean_operator?->value,
            'field' => $this->field?->value,
            'operator' => $this->operator?->value,
            'value' => $this->value,
            'position' => $this->position,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
