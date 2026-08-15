<?php

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Enums\ExtensionPoint;
use App\Domain\Apps\Enums\InstalledAppStatus;
use App\Domain\Apps\Models\App;
use App\Domain\Apps\Models\AppExtension;
use App\Domain\Apps\Models\InstalledApp;
use App\Domain\Apps\Support\ExtensionPointRegistry;
use App\Domain\Automation\Application\CreateWorkflow;
use App\Domain\Automation\Application\DispatchWorkflowTriggersForEvent;
use App\Domain\Automation\Application\PublishWorkflow;
use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Support\WorkflowRunner;
use App\Domain\Automation\Support\WorkflowTriggerRegistry;
use App\Domain\Automation\Support\WorkflowVariableRegistry;
use App\Domain\Customers\Models\Customer;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/**
 * spec section 10: apps register triggers/actions/templates/variables
 * through the existing AppExtension mechanism, "no core changes
 * required." These tests prove an app-contributed action actually gets
 * invoked by the engine (not just stored), and that app-contributed
 * triggers/variables surface in the read-side catalogs.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    Queue::fake();

    [$this->installedAppId] = app(TenantContext::class)->scope($this->store, function () {
        $app = App::factory()->create(['status' => AppStatus::Active->value]);
        $installed = InstalledApp::query()->create([
            'app_id' => $app->id,
            'status' => InstalledAppStatus::Active->value,
            'installed_at' => now(),
        ]);

        return [$installed->id];
    });
});

it('invokes an app-registered automation action when a workflow step references it', function () {
    Http::fake(['https://app.example.test/*' => Http::response(['ok' => true], 200)]);

    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $extension = AppExtension::query()->create([
            'installed_app_id' => $this->installedAppId,
            'extension_point' => ExtensionPoint::AutomationAction->value,
            'config' => ['label' => 'Send to CRM', 'target_url' => 'https://app.example.test/automation-action'],
            'status' => AppStatus::Active->value,
        ]);

        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'App action flow',
            'trigger' => ['event_type' => 'CustomerCreated'],
            'actions' => [['type' => 'app_action', 'config' => ['extension_id' => $extension->id, 'payload' => ['note' => 'hi']]]],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($event, $this->store);
        $execution = WorkflowExecution::query()->where('outbox_event_id', $event->id)->firstOrFail();

        app(WorkflowRunner::class)->run($execution->id);
        $execution->refresh();

        expect($execution->status)->toBe(WorkflowExecutionStatus::Completed);
        Http::assertSent(fn ($request) => $request->url() === 'https://app.example.test/automation-action');
    });
});

it('surfaces an app-registered trigger and variable in the read-side catalogs', function () {
    app(TenantContext::class)->scope($this->store, function () {
        AppExtension::query()->create([
            'installed_app_id' => $this->installedAppId,
            'extension_point' => ExtensionPoint::AutomationTrigger->value,
            'config' => ['event_type' => 'AppOrderSynced', 'label' => 'Order synced to app'],
            'status' => AppStatus::Active->value,
        ]);

        AppExtension::query()->create([
            'installed_app_id' => $this->installedAppId,
            'extension_point' => ExtensionPoint::AutomationVariable->value,
            'config' => ['source' => 'order', 'key' => 'crm_id', 'label' => 'CRM record ID', 'type' => 'string'],
            'status' => AppStatus::Active->value,
        ]);

        $triggers = app(WorkflowTriggerRegistry::class)->all();
        expect($triggers->pluck('event_type'))->toContain('AppOrderSynced');

        $variables = app(WorkflowVariableRegistry::class)->all();
        expect($variables->firstWhere('key', 'crm_id')['label'])->toBe('CRM record ID');
    });
});

it('rejects an automation action extension config with no target_url', function () {
    expect(fn () => ExtensionPointRegistry::assertValidConfig(
        ExtensionPoint::AutomationAction,
        ['label' => 'Missing URL'],
    ))->toThrow(ValidationException::class);
});
