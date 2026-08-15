<?php

namespace App\Domain\Automation\Support;

use App\Domain\Automation\Enums\WorkflowVersionStatus;
use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Models\WorkflowCondition;
use App\Domain\Automation\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves "the version an edit should land on" (spec section 2:
 * editing a never-published workflow mutates its one existing draft in
 * place; editing an already-published workflow creates a new draft
 * instead of touching the live one) — see
 * docs/adr/025-automation-engine.md.
 *
 * A freshly-created draft is seeded from the currently-published
 * version's full content (trigger/conditions/actions) before the
 * caller applies its own changes on top — without this, a partial PATCH
 * (e.g. "just update the actions") would silently produce a draft with
 * no trigger at all, since PATCH semantics mean the caller is not
 * required to resend every field on every request.
 */
final class WorkflowVersioning
{
    public function editableVersion(Workflow $workflow): WorkflowVersion
    {
        $draft = WorkflowVersion::query()
            ->where('workflow_id', $workflow->id)
            ->where('status', WorkflowVersionStatus::Draft->value)
            ->first();

        if ($draft !== null) {
            return $draft;
        }

        $newVersion = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->id,
            'version_number' => $this->nextVersionNumber($workflow),
            'status' => WorkflowVersionStatus::Draft->value,
        ]);

        $published = $workflow->publishedVersion;

        if ($published !== null) {
            $this->cloneVersionContent($published, $newVersion);
        }

        return $newVersion;
    }

    public function nextVersionNumber(Workflow $workflow): int
    {
        return (int) (WorkflowVersion::query()->where('workflow_id', $workflow->id)->max('version_number') ?? 0) + 1;
    }

    public function cloneVersionContent(WorkflowVersion $source, WorkflowVersion $target): void
    {
        $sourceTrigger = $source->trigger;

        if ($sourceTrigger !== null) {
            $target->trigger()->create(['event_type' => $sourceTrigger->event_type]);
        }

        $rootConditions = $source->rootConditions;
        WorkflowConditionTreeLoader::load($rootConditions);
        $this->cloneConditions($rootConditions, $target->id, null);

        foreach ($source->actions as $action) {
            $target->actions()->create(['type' => $action->type->value, 'config' => $action->config, 'position' => $action->position]);
        }
    }

    /**
     * @param  Collection<int, WorkflowCondition>  $conditions
     */
    private function cloneConditions(Collection $conditions, string $targetVersionId, ?string $targetParentId): void
    {
        foreach ($conditions as $condition) {
            $clone = WorkflowCondition::query()->create([
                'workflow_version_id' => $targetVersionId,
                'parent_id' => $targetParentId,
                'boolean_operator' => $condition->boolean_operator?->value,
                'variable_key' => $condition->variable_key,
                'operator' => $condition->operator?->value,
                'value' => $condition->value,
                'position' => $condition->position,
            ]);

            if ($condition->children->isNotEmpty()) {
                $this->cloneConditions($condition->children, $targetVersionId, $clone->id);
            }
        }
    }
}
