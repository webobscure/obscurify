<?php

use App\Domain\Automation\Application\CreateWorkflow;
use App\Domain\Automation\Application\DispatchWorkflowTriggersForEvent;
use App\Domain\Automation\Application\PublishWorkflow;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Support\WorkflowRunner;
use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Application\EnsureDefaultNotificationSetup;
use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultNotificationSetup::class)->handle($this->store);
    });
});

it('runs a workflow action and creates a real Notification, replacing the old internal-notification action', function (string $actionType, string $channel, array $config) {
    app(TenantContext::class)->scope($this->store, function () use ($actionType, $channel, $config) {
        $customer = Customer::factory()->create(['email' => 'ada@example.test', 'phone' => '+15551234567']);

        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Notify on signup',
            'trigger' => ['event_type' => 'CustomerCreated'],
            'conditions' => [],
            'actions' => [['type' => $actionType, 'config' => $config]],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, ['customer_id' => $customer->id]);
        app(DispatchWorkflowTriggersForEvent::class)->handle($event, $this->store);

        $execution = WorkflowExecution::query()->where('workflow_id', $workflow->id)->firstOrFail();
        app(WorkflowRunner::class)->run($execution->id);

        $execution = $execution->fresh();
        expect($execution->status->value)->toBe('completed');

        $notification = Notification::query()->where('channel', $channel)->where('workflow_execution_id', $execution->id)->first();
        expect($notification)->not->toBeNull();
        expect($notification->body_text)->toBe('Welcome!');
    });
})->with([
    'send_email_notification' => ['send_email_notification', 'email', ['subject' => 'Hi', 'body_text' => 'Welcome!']],
    'send_sms_notification' => ['send_sms_notification', 'sms', ['body_text' => 'Welcome!']],
    'send_push_notification' => ['send_push_notification', 'push', ['body_text' => 'Welcome!']],
    'send_in_app_notification' => ['send_in_app_notification', 'in_app', ['body_text' => 'Welcome!']],
    'send_webhook_notification' => ['send_webhook_notification', 'webhook', ['body_text' => 'Welcome!', 'to' => 'https://example.test/hook']],
]);

it('refuses to send when there is no customer in context and no explicit "to" address', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Broken workflow',
            'trigger' => ['event_type' => 'InventoryChanged'],
            'conditions' => [],
            'actions' => [['type' => 'send_email_notification', 'config' => ['body_text' => 'Hi']]],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $event = app(RecordOutboxEvent::class)->handle('InventoryChanged', 'InventoryItem', (string) Str::ulid(), []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($event, $this->store);

        $execution = WorkflowExecution::query()->where('workflow_id', $workflow->id)->firstOrFail();
        app(WorkflowRunner::class)->run($execution->id);

        expect($execution->fresh()->status->value)->toBe('failed');
    });
});
