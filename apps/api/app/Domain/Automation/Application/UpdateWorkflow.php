<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Models\WorkflowTrigger;
use App\Domain\Automation\Support\WorkflowVersioning;
use Illuminate\Support\Facades\DB;

final class UpdateWorkflow
{
    public function __construct(
        private readonly WorkflowVersioning $workflowVersioning,
        private readonly ReplaceWorkflowConditions $replaceWorkflowConditions,
        private readonly ReplaceWorkflowActions $replaceWorkflowActions,
    ) {}

    /**
     * @param  array{name?: string, description?: string|null, trigger?: array{event_type: string}|null, conditions?: list<array<string, mixed>>, actions?: list<array<string, mixed>>}  $data
     */
    public function handle(Workflow $workflow, array $data): Workflow
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow->fill([
                'name' => $data['name'] ?? $workflow->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $workflow->description,
            ])->save();

            $version = $this->workflowVersioning->editableVersion($workflow);

            if (array_key_exists('trigger', $data)) {
                WorkflowTrigger::query()->where('workflow_version_id', $version->id)->delete();

                if (! empty($data['trigger']['event_type'])) {
                    WorkflowTrigger::query()->create([
                        'workflow_version_id' => $version->id,
                        'event_type' => $data['trigger']['event_type'],
                    ]);
                }
            }

            if (array_key_exists('conditions', $data)) {
                $this->replaceWorkflowConditions->handle($version->id, $data['conditions']);
            }

            if (array_key_exists('actions', $data)) {
                $this->replaceWorkflowActions->handle($version->id, $data['actions']);
            }

            return $workflow->fresh();
        });
    }
}
