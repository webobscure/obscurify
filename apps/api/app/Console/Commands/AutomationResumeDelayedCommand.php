<?php

namespace App\Console\Commands;

use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Jobs\RunWorkflowExecutionJob;
use App\Domain\Automation\Models\WorkflowExecution;
use Illuminate\Console\Command;

/**
 * Re-dispatches every `waiting` WorkflowExecution whose time-based delay
 * (`next_resume_at`) has passed — spec section 6: "Wait X minutes/hours/
 * until date." "Wait until event" delays resume through a different
 * path (DispatchWorkflowTriggersForEvent, when the awaited event_type
 * actually arrives), so this command only ever touches rows with
 * `wait_until_event_type IS NULL`. Same "not wired into Laravel's
 * scheduler, run externally on a short cron" convention as
 * webhooks:retry-failed/outbox:process — see
 * docs/architecture/automation.md.
 */
class AutomationResumeDelayedCommand extends Command
{
    protected $signature = 'automation:resume-delayed';

    protected $description = 'Re-dispatch workflow executions whose time-based delay has elapsed';

    public function handle(): int
    {
        $ids = WorkflowExecution::withoutGlobalScopes()
            ->where('status', WorkflowExecutionStatus::Waiting->value)
            ->whereNull('wait_until_event_type')
            ->where('next_resume_at', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            RunWorkflowExecutionJob::dispatch($id);
        }

        $this->info("Resumed {$ids->count()} delayed workflow execution(s).");

        return self::SUCCESS;
    }
}
