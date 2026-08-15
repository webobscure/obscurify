<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Enums\WorkflowStatus;
use App\Domain\Automation\Models\Workflow;

/**
 * Terminal state (spec section 2) — a soft retirement, not a row
 * deletion, so an archived workflow's full version history and
 * execution log stay inspectable. An archived workflow's trigger no
 * longer matches anything (DispatchWorkflowTriggersForEvent only looks
 * at status = published), so this alone is enough to stop it; there is
 * no un-archive path, matching Archived being spec's terminal state.
 */
final class ArchiveWorkflow
{
    public function handle(Workflow $workflow): Workflow
    {
        $workflow->update(['status' => WorkflowStatus::Archived->value]);

        return $workflow->fresh();
    }
}
