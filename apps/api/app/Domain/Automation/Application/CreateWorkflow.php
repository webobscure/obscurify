<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Enums\WorkflowStatus;
use App\Domain\Automation\Enums\WorkflowVersionStatus;
use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Models\WorkflowTrigger;
use App\Domain\Automation\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Workflow and its first (draft) WorkflowVersion in one call —
 * a workflow always has exactly one version from the moment it exists,
 * never zero (spec section 2's lifecycle starts at Draft, not
 * "uncreated"). `trigger` is optional at creation time (an admin may
 * start naming/describing a workflow before picking a trigger in the
 * editor) but PublishWorkflow refuses to publish without one.
 */
final class CreateWorkflow
{
    public function __construct(
        private readonly ReplaceWorkflowConditions $replaceWorkflowConditions,
        private readonly ReplaceWorkflowActions $replaceWorkflowActions,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, trigger?: array{event_type: string}|null, conditions?: list<array<string, mixed>>, actions?: list<array<string, mixed>>}  $data
     */
    public function handle(array $data): Workflow
    {
        return DB::transaction(function () use ($data) {
            $workflow = Workflow::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => WorkflowStatus::Draft->value,
            ]);

            $version = WorkflowVersion::query()->create([
                'workflow_id' => $workflow->id,
                'version_number' => 1,
                'status' => WorkflowVersionStatus::Draft->value,
            ]);

            if (! empty($data['trigger']['event_type'])) {
                WorkflowTrigger::query()->create([
                    'workflow_version_id' => $version->id,
                    'event_type' => $data['trigger']['event_type'],
                ]);
            }

            $this->replaceWorkflowConditions->handle($version->id, $data['conditions'] ?? []);
            $this->replaceWorkflowActions->handle($version->id, $data['actions'] ?? []);

            return $workflow->fresh();
        });
    }
}
