<?php

namespace App\Domain\Automation\Http\Resources;

use App\Domain\Automation\Models\WorkflowExecution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowExecution
 */
final class WorkflowExecutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'workflow_version_id' => $this->workflow_version_id,
            'outbox_event_id' => $this->outbox_event_id,
            'status' => $this->status->value,
            'context' => $this->context,
            'depth' => $this->depth,
            'root_execution_id' => $this->root_execution_id,
            'caused_by_execution_id' => $this->caused_by_execution_id,
            'attempts' => $this->attempts,
            'next_retry_at' => $this->next_retry_at,
            'next_resume_at' => $this->next_resume_at,
            'wait_until_event_type' => $this->wait_until_event_type,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'error_message' => $this->error_message,
            'steps' => WorkflowExecutionStepResource::collection($this->whenLoaded('steps')),
            'created_at' => $this->created_at,
        ];
    }
}
