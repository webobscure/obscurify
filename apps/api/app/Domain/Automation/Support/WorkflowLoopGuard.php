<?php

namespace App\Domain\Automation\Support;

use App\Domain\Automation\Enums\WorkflowActionType;
use App\Domain\Automation\Exceptions\CircularWorkflowException;
use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Models\WorkflowVersion;

/**
 * Loop/recursion prevention (spec section 13). Three independent
 * defenses:
 *
 *  1. Static, publish-time: a workflow whose own "Publish event" action
 *     targets its own trigger's event_type is refused outright
 *     (assertPublishable) — the one cycle shape that's cheap to detect
 *     without walking the whole ancestor chain.
 *  2. Runtime depth cap: every WorkflowExecution caused by another
 *     execution's action inherits depth+1 (see
 *     WorkflowVariableResolver/DispatchWorkflowTriggersForEvent); once
 *     depth exceeds MAX_DEPTH the chain is refused regardless of shape,
 *     which catches indirect/transitive cycles (A -> B -> A) a static
 *     check can't.
 *  3. Rate limiting: caps how many executions one workflow can start per
 *     minute, independent of chain depth — guards against a single
 *     high-frequency trigger (not a cycle at all) overwhelming the queue.
 */
final class WorkflowLoopGuard
{
    public const int MAX_DEPTH = 5;

    public const int MAX_EXECUTIONS_PER_MINUTE = 30;

    public function assertPublishable(WorkflowVersion $version): void
    {
        $trigger = $version->trigger;

        if ($trigger === null) {
            return;
        }

        $selfPublishes = $version->actions()
            ->where('type', WorkflowActionType::PublishEvent->value)
            ->get()
            ->contains(fn ($action) => ($action->config['event_type'] ?? null) === $trigger->event_type);

        if ($selfPublishes) {
            throw CircularWorkflowException::forEventType($trigger->event_type);
        }
    }

    public function depthFor(?WorkflowExecution $causedBy): int
    {
        return $causedBy !== null ? $causedBy->depth + 1 : 0;
    }

    public function rootExecutionIdFor(?WorkflowExecution $causedBy, string $newExecutionId): string
    {
        if ($causedBy === null) {
            return $newExecutionId;
        }

        return $causedBy->root_execution_id ?? $causedBy->id;
    }

    /**
     * Returns a human-readable rejection reason, or null if the
     * execution is allowed to proceed. Deliberately does not throw — a
     * blocked loop should still leave a visible, explained
     * WorkflowExecution row (dead_letter) rather than vanishing
     * silently, so admins can see it in Execution History.
     */
    public function rejectionReasonFor(Workflow $workflow, ?WorkflowExecution $causedBy): ?string
    {
        if ($causedBy !== null && $causedBy->workflow_id === $workflow->id) {
            return 'Blocked: this workflow triggered itself directly.';
        }

        $depth = $this->depthFor($causedBy);

        if ($depth > self::MAX_DEPTH) {
            return "Blocked: automation chain exceeded the maximum depth ({$depth} > ".self::MAX_DEPTH.').';
        }

        $recentCount = WorkflowExecution::query()
            ->where('workflow_id', $workflow->id)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        if ($recentCount >= self::MAX_EXECUTIONS_PER_MINUTE) {
            return 'Blocked: this workflow exceeded its execution rate limit ('.self::MAX_EXECUTIONS_PER_MINUTE.'/minute).';
        }

        return null;
    }
}
