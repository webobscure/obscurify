<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Models\WorkflowCondition;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a WorkflowVersion's entire condition tree atomically (delete
 * then recreate) — the same "the editor always sends the complete tree,
 * never a partial patch" convention as M18's ReplaceSegmentRules, and
 * for the same reason: a workflow's editor UI always submits the whole
 * condition tree on save, so replace-in-full is simpler and safer than
 * diffing.
 */
final class ReplaceWorkflowConditions
{
    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    public function handle(string $workflowVersionId, array $nodes): void
    {
        DB::transaction(function () use ($workflowVersionId, $nodes) {
            WorkflowCondition::query()->where('workflow_version_id', $workflowVersionId)->delete();
            $this->createNodes($workflowVersionId, null, $nodes);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function createNodes(string $workflowVersionId, ?string $parentId, array $nodes): void
    {
        foreach ($nodes as $position => $node) {
            $isGroup = array_key_exists('boolean_operator', $node) && $node['boolean_operator'] !== null;

            $condition = WorkflowCondition::query()->create([
                'workflow_version_id' => $workflowVersionId,
                'parent_id' => $parentId,
                'boolean_operator' => $isGroup ? $node['boolean_operator'] : null,
                'variable_key' => $isGroup ? null : ($node['variable_key'] ?? null),
                'operator' => $isGroup ? null : ($node['operator'] ?? null),
                'value' => $isGroup ? null : ($node['value'] ?? null),
                'position' => $position,
            ]);

            if ($isGroup) {
                $this->createNodes($workflowVersionId, $condition->id, $node['children'] ?? []);
            }
        }
    }
}
