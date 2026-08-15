<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Enums\WorkflowStatus;
use App\Domain\Automation\Enums\WorkflowVersionStatus;
use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Support\WorkflowLoopGuard;
use App\Domain\Automation\Support\WorkflowVersioning;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes the workflow's current editable version (spec section 2:
 * "only one published version per workflow") — demotes whatever was
 * previously published to archived in the same transaction, so
 * `workflows.published_version_id` is never briefly ambiguous.
 */
final class PublishWorkflow
{
    public function __construct(
        private readonly WorkflowVersioning $workflowVersioning,
        private readonly WorkflowLoopGuard $loopGuard,
    ) {}

    public function handle(Workflow $workflow): Workflow
    {
        $version = $this->workflowVersioning->editableVersion($workflow);
        $version->loadMissing('trigger');

        if ($version->trigger === null) {
            throw ValidationException::withMessages(['trigger' => 'A workflow must have a trigger before it can be published.']);
        }

        $this->loopGuard->assertPublishable($version);

        return DB::transaction(function () use ($workflow, $version) {
            if ($workflow->published_version_id !== null) {
                $previous = $workflow->publishedVersion;
                $previous?->update(['status' => WorkflowVersionStatus::Archived->value]);
            }

            $version->update(['status' => WorkflowVersionStatus::Published->value]);
            $workflow->update([
                'status' => WorkflowStatus::Published->value,
                'published_version_id' => $version->id,
            ]);

            return $workflow->fresh();
        });
    }
}
