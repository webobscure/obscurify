<?php

namespace App\Domain\Automation\Http\Resources;

use App\Domain\Automation\Models\WorkflowAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowAction
 */
final class WorkflowActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'config' => $this->config,
            'position' => $this->position,
        ];
    }
}
