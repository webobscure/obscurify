<?php

use App\Domain\Automation\Application\CreateWorkflow;
use App\Domain\Automation\Application\DispatchWorkflowTriggersForEvent;
use App\Domain\Automation\Application\PublishWorkflow;
use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Jobs\RunWorkflowExecutionJob;
use App\Domain\Automation\Models\Task;
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
    // under phpunit.xml's QUEUE_CONNECTION=sync, so each test drives
    // WorkflowRunner::run() with precise, manual control over exactly
    // how many times (and when) an execution actually runs.
    Queue::fake();
});

it('pauses at a wait-X-minutes delay, and only advances once the delay window has passed', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Delayed task',
            'trigger' => ['event_type' => 'CustomerCreated'],
            'actions' => [
                ['type' => 'create_task', 'config' => ['title' => 'before delay']],
                ['type' => 'delay', 'config' => ['delay_type' => 'minutes', 'value' => 30]],
                ['type' => 'create_task', 'config' => ['title' => 'after delay']],
            ],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($event, $this->store);
        $execution = WorkflowExecution::query()->where('outbox_event_id', $event->id)->firstOrFail();

        app(WorkflowRunner::class)->run($execution->id);
        $execution->refresh();

        expect($execution->status)->toBe(WorkflowExecutionStatus::Waiting);
        expect($execution->next_resume_at)->not->toBeNull();
        expect(Task::query()->where('title', 'before delay')->exists())->toBeTrue();
        expect(Task::query()->where('title', 'after delay')->exists())->toBeFalse();

        // Not due yet — resuming again before the window passes must be
        // a no-op (still waiting at the same delay step).
        app(WorkflowRunner::class)->run($execution->id);
        $execution->refresh();
        expect($execution->status)->toBe(WorkflowExecutionStatus::Waiting);
        expect(Task::query()->where('title', 'after delay')->exists())->toBeFalse();

        // automation:resume-delayed only selects rows whose window has
        // passed — force it, then let the command's own (now-real, since
        // this specific run isn't behind Queue::fake) dispatch resume it.
        Queue::fake(); // reset the fake's recorded pushes, still faked
        $execution->update(['next_resume_at' => now()->subMinute()]);
        $this->artisan('automation:resume-delayed')->assertSuccessful();
        Queue::assertPushed(RunWorkflowExecutionJob::class, 1);

        app(WorkflowRunner::class)->run($execution->id);
        $execution->refresh();

        expect($execution->status)->toBe(WorkflowExecutionStatus::Completed);
        expect(Task::query()->where('title', 'after delay')->exists())->toBeTrue();
    });
});

it('pauses on a wait-until-event delay and resumes only when that exact event arrives', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Wait for verification',
            'trigger' => ['event_type' => 'CustomerCreated'],
            'actions' => [
                ['type' => 'delay', 'config' => ['delay_type' => 'until_event', 'event_type' => 'CustomerVerified']],
                ['type' => 'create_task', 'config' => ['title' => 'verified']],
            ],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $created = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($created, $this->store);
        $execution = WorkflowExecution::query()->where('outbox_event_id', $created->id)->firstOrFail();
        app(WorkflowRunner::class)->run($execution->id);

        expect($execution->fresh()->status)->toBe(WorkflowExecutionStatus::Waiting);
        expect($execution->fresh()->wait_until_event_type)->toBe('CustomerVerified');

        // An unrelated event must not resume it.
        $unrelated = app(RecordOutboxEvent::class)->handle('CustomerUpdated', 'Customer', $customer->id, []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($unrelated, $this->store);
        expect($execution->fresh()->status)->toBe(WorkflowExecutionStatus::Waiting);

        $verified = app(RecordOutboxEvent::class)->handle('CustomerVerified', 'Customer', $customer->id, []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($verified, $this->store);
        // DispatchWorkflowTriggersForEvent's resumeEventWaits() claims the
        // execution (status -> pending) and dispatches the job itself —
        // faked here, so the execution is claimed but not yet re-run.
        expect($execution->fresh()->status)->toBe(WorkflowExecutionStatus::Pending);

        app(WorkflowRunner::class)->run($execution->id);
        expect($execution->fresh()->status)->toBe(WorkflowExecutionStatus::Completed);
        expect(Task::query()->where('title', 'verified')->exists())->toBeTrue();
    });
});
