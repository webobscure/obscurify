<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Enums\WorkflowStatus;
use App\Domain\Automation\Models\Workflow;
use Illuminate\Validation\ValidationException;

final class EnableWorkflow
{
    public function handle(Workflow $workflow): Workflow
    {
        if ($workflow->status !== WorkflowStatus::Disabled) {
            throw ValidationException::withMessages(['status' => 'Only a disabled workflow can be re-enabled.']);
        }

        $workflow->update(['status' => WorkflowStatus::Published->value]);

        return $workflow->fresh();
    }
}
