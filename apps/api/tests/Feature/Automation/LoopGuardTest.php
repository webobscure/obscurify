<?php

use App\Domain\Automation\Application\CreateWorkflow;
use App\Domain\Automation\Application\DispatchWorkflowTriggersForEvent;
use App\Domain\Automation\Application\PublishWorkflow;
use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Exceptions\CircularWorkflowException;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Support\WorkflowLoopGuard;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('refuses to publish a workflow whose own action would re-publish its own trigger event', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Self publishing loop',
            'trigger' => ['event_type' => 'LoopEvent'],
            'actions' => [['type' => 'publish_event', 'config' => ['event_type' => 'LoopEvent']]],
        ]);

        expect(fn () => app(PublishWorkflow::class)->handle($workflow))
            ->toThrow(CircularWorkflowException::class);
    });
});

it('blocks a workflow execution that was caused by its own previous execution (direct self-trigger)', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Indirect self trigger',
            'trigger' => ['event_type' => 'LoopEventTwo'],
            // Publishes a *different* event_type so PublishWorkflow's
            // static check doesn't catch it — the runtime depth/self-
            // trigger guard has to catch this one instead.
            'actions' => [['type' => 'publish_event', 'config' => ['event_type' => 'LoopEventTwoBounce']]],
        ]);

        // A second action, added after publish would refuse it, that
        // bounces back to the original trigger — simulate by manually
        // wiring both event types to the same workflow via two triggers
        // is not supported (one trigger per version), so instead prove
        // the depth+self-workflow guard using DispatchWorkflowTriggersForEvent
        // directly with a synthetic caused_by chain.
        app(PublishWorkflow::class)->handle($workflow);

        $firstEvent = app(RecordOutboxEvent::class)->handle('LoopEventTwo', 'Customer', (string) Str::ulid(), []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($firstEvent, $this->store);
        $firstExecution = WorkflowExecution::query()->where('outbox_event_id', $firstEvent->id)->firstOrFail();

        // Simulate this same workflow being triggered again by an event
        // that carries `caused_by_workflow_execution_id` pointing back at
        // an execution of the *same* workflow — exactly what would happen
        // if this workflow's own action's event looped back to itself.
        $loopedEvent = app(RecordOutboxEvent::class)->handle('LoopEventTwo', 'Customer', (string) Str::ulid(), [], $firstExecution->id);
        app(DispatchWorkflowTriggersForEvent::class)->handle($loopedEvent, $this->store);

        $secondExecution = WorkflowExecution::query()->where('outbox_event_id', $loopedEvent->id)->firstOrFail();
        expect($secondExecution->status)->toBe(WorkflowExecutionStatus::DeadLetter);
        expect($secondExecution->error_message)->toContain('triggered itself directly');
    });
});

it('blocks an execution chain once it exceeds the maximum depth', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $workflowA = app(CreateWorkflow::class)->handle([
            'name' => 'Chain A',
            'trigger' => ['event_type' => 'ChainStepA'],
            'actions' => [['type' => 'publish_event', 'config' => ['event_type' => 'ChainStepB']]],
        ]);
        app(PublishWorkflow::class)->handle($workflowA);

        $workflowB = app(CreateWorkflow::class)->handle([
            'name' => 'Chain B',
            'trigger' => ['event_type' => 'ChainStepB'],
            'actions' => [['type' => 'publish_event', 'config' => ['event_type' => 'ChainStepA']]],
        ]);
        app(PublishWorkflow::class)->handle($workflowB);

        // Manually simulate a chain already deep enough that the next
        // hop must be refused, by directly dispatching with a fabricated
        // "caused by" execution at depth = MAX_DEPTH.
        $deepExecution = WorkflowExecution::query()->create([
            'workflow_id' => $workflowA->id,
            'workflow_version_id' => $workflowA->fresh()->published_version_id,
            'outbox_event_id' => app(RecordOutboxEvent::class)->handle('ChainStepA', 'Customer', (string) Str::ulid(), [])->id,
            'status' => WorkflowExecutionStatus::Completed->value,
            'depth' => WorkflowLoopGuard::MAX_DEPTH,
        ]);

        $nextEvent = app(RecordOutboxEvent::class)->handle('ChainStepB', 'Customer', (string) Str::ulid(), [], $deepExecution->id);
        app(DispatchWorkflowTriggersForEvent::class)->handle($nextEvent, $this->store);

        $nextExecution = WorkflowExecution::query()->where('outbox_event_id', $nextEvent->id)->firstOrFail();
        expect($nextExecution->status)->toBe(WorkflowExecutionStatus::DeadLetter);
        expect($nextExecution->error_message)->toContain('maximum depth');
        expect($nextExecution->depth)->toBe(WorkflowLoopGuard::MAX_DEPTH + 1);
    });
});
