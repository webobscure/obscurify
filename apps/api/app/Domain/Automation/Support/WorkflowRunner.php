<?php

namespace App\Domain\Automation\Support;

use App\Domain\Automation\Enums\DelayType;
use App\Domain\Automation\Enums\WorkflowActionType;
use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Enums\WorkflowExecutionStepStatus;
use App\Domain\Automation\Enums\WorkflowExecutionStepType;
use App\Domain\Automation\Models\WorkflowAction;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Models\WorkflowExecutionStep;
use App\Domain\Automation\Models\WorkflowVersion;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Orchestrates one WorkflowExecution: evaluate conditions once, then run
 * actions in order, pausing at a `delay` action and resuming later
 * (spec section 6), retrying a failed action with backoff, and finally
 * marking the whole execution dead-letter once retries are exhausted
 * (spec section 8). Deliberately does not wrap the run in one long DB
 * transaction — actions make real HTTP calls (CallAppWebhook, AppAction)
 * and a delay can pause for hours or days, so holding a row lock for
 * that whole span is wrong; instead each step's WorkflowExecutionStep
 * row is its own small write, and the execution is claimed via a single
 * guarded UPDATE (an optimistic lock, not a held one) so two workers
 * picking up the same execution can't both run it — see
 * CustomerGroupMembershipConcurrencyTest's sibling precedent (M18) and
 * WorkflowExecutionConcurrencyTest for this milestone's own coverage.
 *
 * Retry/backoff/dead-letter mirrors DeliverWebhookJob (Milestone 11)
 * exactly — same MAX_ATTEMPTS, same backoff schedule — since both are
 * "keep retrying a side effect with growing delays, then give up
 * loudly" problems with the same shape.
 */
final class WorkflowRunner
{
    public const int MAX_ATTEMPTS = 6;

    /**
     * @var int[]
     */
    private const array BACKOFF_SECONDS = [10, 30, 60, 300, 900, 3600];

    public function __construct(
        private readonly WorkflowConditionEvaluator $conditionEvaluator,
        private readonly WorkflowActionExecutor $actionExecutor,
    ) {}

    public function run(string $workflowExecutionId): void
    {
        $execution = WorkflowExecution::withoutGlobalScopes()->find($workflowExecutionId);

        if ($execution === null) {
            return;
        }

        $claimable = [
            WorkflowExecutionStatus::Pending->value,
            WorkflowExecutionStatus::Waiting->value,
            WorkflowExecutionStatus::Failed->value,
        ];

        $claimed = WorkflowExecution::withoutGlobalScopes()
            ->whereKey($execution->id)
            ->whereIn('status', $claimable)
            ->update(['status' => WorkflowExecutionStatus::Running->value]);

        if ($claimed !== 1) {
            return;
        }

        $execution = $execution->fresh();

        if ($execution === null) {
            return;
        }

        if ($execution->started_at === null) {
            $execution->update(['started_at' => now()]);
        }

        $version = WorkflowVersion::withoutGlobalScopes()->find($execution->workflow_version_id);

        if ($version === null) {
            $execution->update(['status' => WorkflowExecutionStatus::DeadLetter->value, 'error_message' => 'Workflow version no longer exists.', 'completed_at' => now()]);

            return;
        }

        if (! $this->hasConditionStep($execution)) {
            if (! $this->evaluateConditions($execution, $version)) {
                $execution->update(['status' => WorkflowExecutionStatus::Completed->value, 'completed_at' => now()]);

                return;
            }
        }

        $this->runActions($execution, $version);
    }

    private function hasConditionStep(WorkflowExecution $execution): bool
    {
        return WorkflowExecutionStep::withoutGlobalScopes()
            ->where('workflow_execution_id', $execution->id)
            ->where('step_type', WorkflowExecutionStepType::Condition->value)
            ->exists();
    }

    private function evaluateConditions(WorkflowExecution $execution, WorkflowVersion $version): bool
    {
        $rootConditions = $version->rootConditions;
        WorkflowConditionTreeLoader::load($rootConditions);

        $passed = $this->conditionEvaluator->evaluate($rootConditions, $execution->context);

        WorkflowExecutionStep::withoutGlobalScopes()->create([
            'store_id' => $execution->store_id,
            'workflow_execution_id' => $execution->id,
            'workflow_action_id' => null,
            'step_type' => WorkflowExecutionStepType::Condition->value,
            'status' => $passed ? WorkflowExecutionStepStatus::Succeeded->value : WorkflowExecutionStepStatus::Skipped->value,
            'output' => ['passed' => $passed],
            'position' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return $passed;
    }

    private function runActions(WorkflowExecution $execution, WorkflowVersion $version): void
    {
        $actions = $version->actions->values();

        for ($index = $execution->current_action_position; $index < $actions->count(); $index++) {
            /** @var WorkflowAction $action */
            $action = $actions[$index];

            if ($action->type === WorkflowActionType::Delay) {
                if ($this->resumePastDelay($execution, $action, $index)) {
                    continue;
                }

                return;
            }

            $step = WorkflowExecutionStep::withoutGlobalScopes()->create([
                'store_id' => $execution->store_id,
                'workflow_execution_id' => $execution->id,
                'workflow_action_id' => $action->id,
                'step_type' => WorkflowExecutionStepType::Action->value,
                'status' => WorkflowExecutionStepStatus::Pending->value,
                'input' => $action->config,
                'position' => $index + 1,
                'started_at' => now(),
            ]);

            try {
                $output = $this->actionExecutor->execute($action, $execution->context, $execution, $this->priorStepOutputs($execution));

                $step->update(['status' => WorkflowExecutionStepStatus::Succeeded->value, 'output' => $output, 'completed_at' => now()]);
                $execution->update(['current_action_position' => $index + 1]);
            } catch (Throwable $e) {
                $step->update(['status' => WorkflowExecutionStepStatus::Failed->value, 'error_message' => substr($e->getMessage(), 0, 500), 'completed_at' => now()]);
                $this->handleFailure($execution, $e);

                return;
            }
        }

        $execution->update(['status' => WorkflowExecutionStatus::Completed->value, 'completed_at' => now()]);
    }

    /**
     * Returns true once this delay is already satisfied (resuming past
     * it), false if it is still waiting (either just entered for the
     * first time, or re-entered before it's actually due — execution
     * stays paused either way).
     */
    private function resumePastDelay(WorkflowExecution $execution, WorkflowAction $action, int $index): bool
    {
        $waitingStep = WorkflowExecutionStep::withoutGlobalScopes()
            ->where('workflow_execution_id', $execution->id)
            ->where('workflow_action_id', $action->id)
            ->where('status', WorkflowExecutionStepStatus::Waiting->value)
            ->first();

        if ($waitingStep === null) {
            $this->beginDelay($execution, $action, $index);

            return false;
        }

        // Time-based delays are self-verifiable — the runner is the
        // authority on whether the window has actually passed, not just
        // whatever caused run() to be invoked again (defense in depth:
        // a stray duplicate dispatch must not resume a delay early).
        // Event-based delays (next_resume_at stays null — see
        // beginDelay) have no such signal on this row; the only
        // legitimate path back into a second run() call for one is
        // DispatchWorkflowTriggersForEvent::resumeEventWaits(), which
        // already validated the matching event before re-dispatching,
        // so that case is trusted here.
        if ($execution->next_resume_at !== null && $execution->next_resume_at->isFuture()) {
            // The initial claim (top of run()) already flipped status to
            // Running before this check could run — revert it, since
            // this delay genuinely isn't due yet.
            $execution->update(['status' => WorkflowExecutionStatus::Waiting->value]);

            return false;
        }

        $waitingStep->update(['status' => WorkflowExecutionStepStatus::Succeeded->value, 'completed_at' => now()]);
        $execution->update([
            'current_action_position' => $index + 1,
            'next_resume_at' => null,
            'wait_until_event_type' => null,
        ]);

        return true;
    }

    private function beginDelay(WorkflowExecution $execution, WorkflowAction $action, int $index): void
    {
        $config = $action->config;
        $delayType = DelayType::tryFrom($config['delay_type'] ?? '') ?? DelayType::Minutes;

        $resumeAt = match ($delayType) {
            DelayType::Minutes => now()->addMinutes(max(0, (int) ($config['value'] ?? 0))),
            DelayType::Hours => now()->addHours(max(0, (int) ($config['value'] ?? 0))),
            DelayType::UntilDate => isset($config['until']) ? Carbon::parse($config['until']) : now(),
            DelayType::UntilEvent => null,
        };

        WorkflowExecutionStep::withoutGlobalScopes()->create([
            'store_id' => $execution->store_id,
            'workflow_execution_id' => $execution->id,
            'workflow_action_id' => $action->id,
            'step_type' => WorkflowExecutionStepType::Action->value,
            'status' => WorkflowExecutionStepStatus::Waiting->value,
            'input' => $config,
            'position' => $index + 1,
            'started_at' => now(),
        ]);

        $execution->update([
            'status' => WorkflowExecutionStatus::Waiting->value,
            'next_resume_at' => $resumeAt,
            'wait_until_event_type' => $delayType === DelayType::UntilEvent ? ($config['event_type'] ?? null) : null,
        ]);
    }

    private function handleFailure(WorkflowExecution $execution, Throwable $e): void
    {
        $attempt = $execution->attempts + 1;
        $exhausted = $attempt >= self::MAX_ATTEMPTS;

        $execution->update([
            'attempts' => $attempt,
            'error_message' => substr($e->getMessage(), 0, 500),
            'status' => $exhausted ? WorkflowExecutionStatus::DeadLetter->value : WorkflowExecutionStatus::Failed->value,
            'next_retry_at' => $exhausted ? null : now()->addSeconds(self::BACKOFF_SECONDS[$attempt - 1] ?? 3600),
            'completed_at' => $exhausted ? now() : null,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function priorStepOutputs(WorkflowExecution $execution): array
    {
        return WorkflowExecutionStep::withoutGlobalScopes()
            ->where('workflow_execution_id', $execution->id)
            ->where('step_type', WorkflowExecutionStepType::Action->value)
            ->where('status', WorkflowExecutionStepStatus::Succeeded->value)
            ->whereNotNull('workflow_action_id')
            ->get()
            ->mapWithKeys(fn (WorkflowExecutionStep $step) => [$step->workflow_action_id => $step->output ?? []])
            ->all();
    }
}
