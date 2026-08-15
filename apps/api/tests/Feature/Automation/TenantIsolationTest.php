<?php

use App\Domain\Automation\Application\CreateWorkflow;
use App\Domain\Automation\Application\DispatchWorkflowTriggersForEvent;
use App\Domain\Automation\Application\PublishWorkflow;
use App\Domain\Automation\Application\RegisterBuiltInAutomationCatalog;
use App\Domain\Automation\Models\Task;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Models\WorkflowTemplate;
use App\Domain\Customers\Models\Customer;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    Queue::fake();
});

it('never lists, shows, or lets one store publish/execute another store\'s workflows', function () {
    $workflowA = app(TenantContext::class)->scope($this->storeA, fn () => app(CreateWorkflow::class)->handle([
        'name' => 'Store A workflow',
        'trigger' => ['event_type' => 'CustomerCreated'],
    ]));

    $listB = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/automation/workflows', tenantHeader($this->storeB));
    $listB->assertOk()->assertJsonCount(0, 'data');

    $showB = $this->actingAs($this->userB, 'sanctum')->getJson("/api/v1/automation/workflows/{$workflowA->id}", tenantHeader($this->storeB));
    $showB->assertNotFound();

    $publishB = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/automation/workflows/{$workflowA->id}/publish", [], tenantHeader($this->storeB));
    $publishB->assertNotFound();
});

it('does not execute store B\'s workflow when store A fires the same trigger event_type', function () {
    app(TenantContext::class)->scope($this->storeB, function () {
        $workflowB = app(CreateWorkflow::class)->handle([
            'name' => 'Store B listens',
            'trigger' => ['event_type' => 'SharedTriggerName'],
            'actions' => [['type' => 'create_task', 'config' => ['title' => 'should never run for store A']]],
        ]);
        app(PublishWorkflow::class)->handle($workflowB);
    });

    app(TenantContext::class)->scope($this->storeA, function () {
        $customer = Customer::factory()->create();
        $event = app(RecordOutboxEvent::class)->handle('SharedTriggerName', 'Customer', $customer->id, []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($event, $this->storeA);

        expect(WorkflowExecution::query()->count())->toBe(0);
    });

    app(TenantContext::class)->scope($this->storeB, function () {
        expect(Task::query()->where('title', 'should never run for store A')->exists())->toBeFalse();
    });
});

it('exposes executions scoped strictly to the requesting store', function () {
    [$executionAId] = app(TenantContext::class)->scope($this->storeA, function () {
        $customer = Customer::factory()->create();
        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Store A execution source',
            'trigger' => ['event_type' => 'CustomerCreated'],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($event, $this->storeA);

        return [WorkflowExecution::query()->firstOrFail()->id];
    });

    $listB = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/automation/executions', tenantHeader($this->storeB));
    $listB->assertOk()->assertJsonCount(0, 'data');

    $showB = $this->actingAs($this->userB, 'sanctum')->getJson("/api/v1/automation/executions/{$executionAId}", tenantHeader($this->storeB));
    $showB->assertNotFound();
});

it('templates are a global catalog visible identically to every store', function () {
    app(RegisterBuiltInAutomationCatalog::class)->handle();

    $listA = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/automation/templates', tenantHeader($this->storeA));
    $listB = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/automation/templates', tenantHeader($this->storeB));

    expect($listA->json('data'))->toHaveCount(WorkflowTemplate::count());
    expect(collect($listA->json('data'))->pluck('key')->sort()->values())
        ->toEqual(collect($listB->json('data'))->pluck('key')->sort()->values());
});
