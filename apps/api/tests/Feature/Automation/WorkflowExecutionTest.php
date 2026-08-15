<?php

use App\Domain\Automation\Application\CreateWorkflow;
use App\Domain\Automation\Application\DisableWorkflow;
use App\Domain\Automation\Application\DispatchWorkflowTriggersForEvent;
use App\Domain\Automation\Application\PublishWorkflow;
use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Models\Task;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Support\WorkflowRunner;
use App\Domain\CustomerIntelligence\Models\CustomerTag;
use App\Domain\Customers\Models\Customer;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

/**
 * Fires a Customer-scoped trigger event and runs whatever
 * WorkflowExecution it produces exactly once. `Queue::fake()` prevents
 * DispatchWorkflowTriggersForEvent's own RunWorkflowExecutionJob::dispatch()
 * from auto-executing under phpunit.xml's `QUEUE_CONNECTION=sync` (which
 * would otherwise run the execution a first time before this helper's
 * own explicit run — see docs/architecture/automation.md's test notes),
 * so every test drives WorkflowRunner::run() with full, precise control.
 */
function fireCustomerTrigger(string $storeId, string $eventType, string $customerId): WorkflowExecution
{
    Queue::fake();

    $event = app(RecordOutboxEvent::class)->handle($eventType, 'Customer', $customerId, ['customer_id' => $customerId, 'store_id' => $storeId]);
    app(DispatchWorkflowTriggersForEvent::class)->handle($event, Store::find($storeId));

    $execution = WorkflowExecution::query()->where('outbox_event_id', $event->id)->firstOrFail();
    app(WorkflowRunner::class)->run($execution->id);

    return $execution->fresh();
}

it('runs a matching published workflow end to end, including a reused Customer Intelligence action', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();
        $tag = CustomerTag::factory()->create();

        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Tag on create',
            'trigger' => ['event_type' => 'CustomerCreated'],
            'conditions' => [],
            'actions' => [['type' => 'add_customer_tag', 'config' => ['tag_id' => $tag->id]]],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $execution = fireCustomerTrigger($this->store->id, 'CustomerCreated', $customer->id);

        expect($execution->status)->toBe(WorkflowExecutionStatus::Completed);
        expect($customer->tagAssignments()->where('customer_tag_id', $tag->id)->exists())->toBeTrue();
    });
});

it('skips actions and completes immediately when the condition tree fails', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create(['status' => 'active']);
        $tag = CustomerTag::factory()->create();

        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Only inactive customers',
            'trigger' => ['event_type' => 'CustomerCreated'],
            'conditions' => [['variable_key' => 'customer.status', 'operator' => 'equals', 'value' => 'inactive']],
            'actions' => [['type' => 'add_customer_tag', 'config' => ['tag_id' => $tag->id]]],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $execution = fireCustomerTrigger($this->store->id, 'CustomerCreated', $customer->id);

        expect($execution->status)->toBe(WorkflowExecutionStatus::Completed);
        expect($customer->tagAssignments()->where('customer_tag_id', $tag->id)->exists())->toBeFalse();
        expect($execution->steps()->count())->toBe(1); // only the condition step, no action step
    });
});

it('does not start an execution for a disabled or draft workflow', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        app(CreateWorkflow::class)->handle([
            'name' => 'Never published',
            'trigger' => ['event_type' => 'CustomerCreated'],
        ]);

        $published = app(CreateWorkflow::class)->handle([
            'name' => 'Will be disabled',
            'trigger' => ['event_type' => 'CustomerCreated'],
        ]);
        app(PublishWorkflow::class)->handle($published);
        app(DisableWorkflow::class)->handle($published->fresh());

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($event, $this->store);

        expect(WorkflowExecution::query()->count())->toBe(0);
    });
});

it('evaluates AND/OR nested condition groups correctly', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create(['status' => 'active', 'email' => 'match@example.test']);

        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Nested conditions',
            'trigger' => ['event_type' => 'CustomerCreated'],
            'conditions' => [
                [
                    'boolean_operator' => 'and',
                    'children' => [
                        ['variable_key' => 'customer.status', 'operator' => 'equals', 'value' => 'active'],
                        [
                            'boolean_operator' => 'or',
                            'children' => [
                                ['variable_key' => 'customer.email', 'operator' => 'equals', 'value' => 'nomatch@example.test'],
                                ['variable_key' => 'customer.email', 'operator' => 'equals', 'value' => 'match@example.test'],
                            ],
                        ],
                    ],
                ],
            ],
            'actions' => [['type' => 'create_task', 'config' => ['title' => 'matched']]],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $execution = fireCustomerTrigger($this->store->id, 'CustomerCreated', $customer->id);

        expect($execution->status)->toBe(WorkflowExecutionStatus::Completed);
        expect(Task::query()->where('title', 'matched')->exists())->toBeTrue();
    });
});
