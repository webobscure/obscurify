<?php

use App\Domain\Automation\Application\CreateWorkflow;
use App\Domain\Automation\Application\DispatchWorkflowTriggersForEvent;
use App\Domain\Automation\Application\PublishWorkflow;
use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Support\WorkflowRunner;
use App\Domain\Customers\Models\Customer;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    // Prevents RunWorkflowExecutionJob::dispatch() from auto-executing
    // under phpunit.xml's QUEUE_CONNECTION=sync — see DelayTest.php.
    Queue::fake();
});

it('retries a failing action with backoff, then moves the execution to dead_letter once attempts are exhausted', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Always fails',
            'trigger' => ['event_type' => 'CustomerCreated'],
            // References a tag that will never exist — every attempt fails.
            'actions' => [['type' => 'add_customer_tag', 'config' => ['tag_id' => 'does-not-exist']]],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($event, $this->store);
        $execution = WorkflowExecution::query()->where('outbox_event_id', $event->id)->firstOrFail();

        app(WorkflowRunner::class)->run($execution->id);
        $execution->refresh();
        expect($execution->status)->toBe(WorkflowExecutionStatus::Failed);
        expect($execution->attempts)->toBe(1);
        expect($execution->next_retry_at)->not->toBeNull();
        expect($execution->error_message)->not->toBeNull();

        for ($i = 2; $i <= WorkflowRunner::MAX_ATTEMPTS; $i++) {
            app(WorkflowRunner::class)->run($execution->id);
            $execution->refresh();
            expect($execution->attempts)->toBe($i);
        }

        expect($execution->status)->toBe(WorkflowExecutionStatus::DeadLetter);
        expect($execution->next_retry_at)->toBeNull();
        expect($execution->completed_at)->not->toBeNull();

        // A dead_letter execution is terminal — the retry command must
        // not pick it up (it only ever selects status = failed).
        $this->artisan('automation:retry-failed')->assertSuccessful();
        expect($execution->fresh()->status)->toBe(WorkflowExecutionStatus::DeadLetter);
    });
});
