<?php

namespace App\Domain\Automation\Http\Resources;

use App\Domain\Automation\Models\WorkflowVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowVersion
 */
final class WorkflowVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'status' => $this->status->value,
            'trigger' => $this->whenLoaded('trigger', fn () => $this->trigger !== null ? ['event_type' => $this->trigger->event_type] : null),
            'conditions' => WorkflowConditionResource::collection($this->whenLoaded('rootConditions')),
            'actions' => WorkflowActionResource::collection($this->whenLoaded('actions')),
            'created_at' => $this->created_at,
        ];
    }
}
