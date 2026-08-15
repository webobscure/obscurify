<?php

namespace App\Domain\Automation\Jobs;

use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Support\WorkflowRunner;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs (or resumes) one WorkflowExecution — dispatched by
 * DispatchWorkflowTriggersForEvent on a fresh trigger, and re-dispatched
 * by the delay/retry resumption commands. Establishes TenantContext
 * before calling WorkflowRunner, exactly as DeliverWebhookJob does,
 * since every write inside the runner (WorkflowExecutionStep creates,
 * and every reused Customer Intelligence/Promotions application
 * service the ActionExecutor calls) goes through BelongsToTenant, which
 * requires an active tenant to create anything.
 */
final class RunWorkflowExecutionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $workflowExecutionId) {}

    public function handle(TenantContext $tenantContext, WorkflowRunner $runner): void
    {
        $execution = WorkflowExecution::withoutGlobalScopes()->find($this->workflowExecutionId);

        if ($execution === null) {
            return;
        }

        $store = Store::query()->find($execution->store_id);

        if ($store === null) {
            return;
        }

        $tenantContext->scope($store, fn () => $runner->run($this->workflowExecutionId));
    }
}
