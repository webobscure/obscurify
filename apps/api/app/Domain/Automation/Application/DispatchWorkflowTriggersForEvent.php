<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Enums\WorkflowStatus;
use App\Domain\Automation\Jobs\RunWorkflowExecutionJob;
use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Support\WorkflowLoopGuard;
use App\Domain\Automation\Support\WorkflowVariableResolver;
use App\Domain\Stores\Models\Store;
use App\Shared\Commerce\Models\OutboxEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The trigger side of the engine (spec section 3) — called from
 * ProcessOutboxEventsCommand, inside the same tenant scope/transaction
 * that claims each OutboxEvent, exactly alongside DispatchWebhooksForEvent
 * (both are independent consumers of the same event; see
 * docs/adr/025-automation-engine.md for why this reuses that hook point
 * rather than a second outbox-polling command). Two independent jobs:
 *
 *  1. Start a new WorkflowExecution for every Published workflow whose
 *     trigger matches this event_type — idempotent via the unique
 *     constraint on (workflow_version_id, outbox_event_id), the same
 *     claim-or-skip pattern WebhookDelivery uses for its own fan-out.
 *  2. Resume any WorkflowExecution paused on a "wait until event" delay
 *     (spec section 6) whose `wait_until_event_type` matches this event,
 *     in the same store.
 */
final class DispatchWorkflowTriggersForEvent
{
    public function __construct(
        private readonly WorkflowVariableResolver $variableResolver,
        private readonly WorkflowLoopGuard $loopGuard,
    ) {}

    public function handle(OutboxEvent $event, Store $store): void
    {
        $this->startNewExecutions($event, $store);
        $this->resumeEventWaits($event);
    }

    private function startNewExecutions(OutboxEvent $event, Store $store): void
    {
        $workflows = Workflow::query()
            ->where('status', WorkflowStatus::Published->value)
            ->whereHas('publishedVersion.trigger', fn ($query) => $query->where('event_type', $event->event_type))
            ->with('publishedVersion.trigger')
            ->get();

        if ($workflows->isEmpty()) {
            return;
        }

        $causedBy = $event->caused_by_workflow_execution_id !== null
            ? WorkflowExecution::query()->find($event->caused_by_workflow_execution_id)
            : null;

        $context = $this->variableResolver->resolve($event, $store);

        foreach ($workflows as $workflow) {
            $this->startExecution($workflow, $event, $context, $causedBy);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function startExecution(Workflow $workflow, OutboxEvent $event, array $context, ?WorkflowExecution $causedBy): void
    {
        $version = $workflow->publishedVersion;

        if ($version === null) {
            return;
        }

        try {
            $execution = DB::transaction(fn () => WorkflowExecution::query()->create([
                'workflow_id' => $workflow->id,
                'workflow_version_id' => $version->id,
                'outbox_event_id' => $event->id,
                'status' => WorkflowExecutionStatus::Pending->value,
                'context' => $context,
                'depth' => $this->loopGuard->depthFor($causedBy),
                'caused_by_execution_id' => $causedBy?->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Already claimed by a concurrent/earlier dispatch for this
            // exact (version, event) pair.
            return;
        }

        $execution->update(['root_execution_id' => $this->loopGuard->rootExecutionIdFor($causedBy, $execution->id)]);

        $rejectionReason = $this->loopGuard->rejectionReasonFor($workflow, $causedBy);

        if ($rejectionReason !== null) {
            $execution->update([
                'status' => WorkflowExecutionStatus::DeadLetter->value,
                'error_message' => $rejectionReason,
                'completed_at' => now(),
            ]);

            return;
        }

        RunWorkflowExecutionJob::dispatch($execution->id);
    }

    private function resumeEventWaits(OutboxEvent $event): void
    {
        $waiting = WorkflowExecution::query()
            ->where('status', WorkflowExecutionStatus::Waiting->value)
            ->where('wait_until_event_type', $event->event_type)
            ->get();

        foreach ($waiting as $execution) {
            $claimed = WorkflowExecution::query()
                ->whereKey($execution->id)
                ->where('status', WorkflowExecutionStatus::Waiting->value)
                ->update(['status' => WorkflowExecutionStatus::Pending->value]);

            if ($claimed === 1) {
                RunWorkflowExecutionJob::dispatch($execution->id);
            }
        }
    }
}
