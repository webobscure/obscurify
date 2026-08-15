<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Enums\WorkflowStatus;
use App\Domain\Automation\Models\Workflow;
use Illuminate\Validation\ValidationException;

/**
 * Disabling stops new triggers from starting executions (the runner
 * checks Workflow::status, not WorkflowVersion::status) without
 * touching the published version at all — re-enabling is instant and
 * loses no configuration.
 */
final class DisableWorkflow
{
    public function handle(Workflow $workflow): Workflow
    {
        if ($workflow->status !== WorkflowStatus::Published) {
            throw ValidationException::withMessages(['status' => 'Only a published workflow can be disabled.']);
        }

        $workflow->update(['status' => WorkflowStatus::Disabled->value]);

        return $workflow->fresh();
    }
}
