<?php

namespace App\Domain\Automation\Http\Resources;

use App\Domain\Automation\Models\WorkflowExecutionStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowExecutionStep
 */
final class WorkflowExecutionStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_action_id' => $this->workflow_action_id,
            'step_type' => $this->step_type->value,
            'status' => $this->status->value,
            'input' => $this->input,
            'output' => $this->output,
            'error_message' => $this->error_message,
            'attempts' => $this->attempts,
            'position' => $this->position,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
