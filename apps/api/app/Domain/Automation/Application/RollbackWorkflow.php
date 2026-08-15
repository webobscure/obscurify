<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Enums\WorkflowVersionStatus;
use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Models\WorkflowVersion;
use App\Domain\Automation\Support\WorkflowVersioning;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Rollback" clones an old (archived) version's trigger/conditions/
 * actions into a brand-new version and publishes it — it never
 * un-archives history in place, so `version_number` stays strictly
 * increasing and every version a workflow ever had remains an
 * immutable, inspectable snapshot (spec section 2: "Support rollback").
 */
final class RollbackWorkflow
{
    public function __construct(
        private readonly WorkflowVersioning $workflowVersioning,
        private readonly PublishWorkflow $publishWorkflow,
    ) {}

    public function handle(Workflow $workflow, WorkflowVersion $target): Workflow
    {
        if ($target->workflow_id !== $workflow->id) {
            throw ValidationException::withMessages(['version' => 'That version does not belong to this workflow.']);
        }

        DB::transaction(function () use ($workflow, $target) {
            $newVersion = WorkflowVersion::query()->create([
                'workflow_id' => $workflow->id,
                'version_number' => $this->workflowVersioning->nextVersionNumber($workflow),
                'status' => WorkflowVersionStatus::Draft->value,
            ]);

            $this->workflowVersioning->cloneVersionContent($target, $newVersion);
        });

        return $this->publishWorkflow->handle($workflow);
    }
}
