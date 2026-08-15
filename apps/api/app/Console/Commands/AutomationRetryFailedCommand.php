<?php

namespace App\Console\Commands;

use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Jobs\RunWorkflowExecutionJob;
use App\Domain\Automation\Models\WorkflowExecution;
use Illuminate\Console\Command;

/**
 * Re-dispatches every `failed` WorkflowExecution whose backoff window
 * (`next_retry_at`) has passed — mirrors
 * RetryFailedWebhookDeliveriesCommand exactly, including relying on the
 * job's own atomic claim (WorkflowRunner::run()'s guarded status
 * UPDATE) rather than pre-transitioning status here, so a doubly-
 * dispatched retry is a safe no-op rather than a race.
 */
class AutomationRetryFailedCommand extends Command
{
    protected $signature = 'automation:retry-failed';

    protected $description = 'Re-dispatch failed workflow executions whose backoff window has passed';

    public function handle(): int
    {
        $ids = WorkflowExecution::withoutGlobalScopes()
            ->where('status', WorkflowExecutionStatus::Failed->value)
            ->where('next_retry_at', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            RunWorkflowExecutionJob::dispatch($id);
        }

        $this->info("Re-dispatched {$ids->count()} failed workflow execution(s).");

        return self::SUCCESS;
    }
}
