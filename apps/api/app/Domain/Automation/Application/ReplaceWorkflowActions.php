<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Models\WorkflowAction;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a WorkflowVersion's entire ordered action list atomically —
 * same delete-then-recreate convention as ReplaceWorkflowConditions.
 */
final class ReplaceWorkflowActions
{
    /**
     * @param  list<array{type: string, config?: array<string, mixed>}>  $actions
     */
    public function handle(string $workflowVersionId, array $actions): void
    {
        DB::transaction(function () use ($workflowVersionId, $actions) {
            WorkflowAction::query()->where('workflow_version_id', $workflowVersionId)->delete();

            foreach ($actions as $position => $action) {
                WorkflowAction::query()->create([
                    'workflow_version_id' => $workflowVersionId,
                    'type' => $action['type'],
                    'config' => $action['config'] ?? [],
                    'position' => $position,
                ]);
            }
        });
    }
}
