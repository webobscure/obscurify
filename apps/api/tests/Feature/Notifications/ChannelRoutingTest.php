<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Application\DispatchNotificationsForEvent;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationEvent;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('routes a matching platform event to its configured template and channel', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create(['first_name' => 'Ada']);

        $template = NotificationTemplate::query()->create([
            'name' => 'Welcome email', 'channel' => 'email',
            'subject' => 'Welcome, {{customer.first_name}}!', 'body_text' => 'Thanks for joining, {{customer.first_name}}.',
        ]);

        NotificationEvent::query()->create([
            'event_type' => 'CustomerCreated', 'channel' => 'email', 'template_id' => $template->id, 'is_enabled' => true,
        ]);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, ['customer_id' => $customer->id]);
        app(DispatchNotificationsForEvent::class)->handle($event, $this->store);

        $notification = Notification::query()->where('event_type', 'CustomerCreated')->firstOrFail();
        expect($notification->channel->value)->toBe('email');
        expect($notification->subject)->toBe('Welcome, Ada!');
        expect($notification->body_text)->toBe('Thanks for joining, Ada.');
        expect($notification->recipients()->where('customer_id', $customer->id)->exists())->toBeTrue();
    });
});

it('does not route an event with no matching NotificationEvent rule', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchNotificationsForEvent::class)->handle($event, $this->store);

        expect(Notification::query()->count())->toBe(0);
    });
});

it('skips a disabled routing rule', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $template = NotificationTemplate::query()->create([
            'name' => 'Welcome email', 'channel' => 'email', 'body_text' => 'Hi',
        ]);

        NotificationEvent::query()->create([
            'event_type' => 'CustomerCreated', 'channel' => 'email', 'template_id' => $template->id, 'is_enabled' => false,
        ]);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchNotificationsForEvent::class)->handle($event, $this->store);

        expect(Notification::query()->count())->toBe(0);
    });
});

it('skips a rule pointing at an inactive template', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $template = NotificationTemplate::query()->create([
            'name' => 'Welcome email', 'channel' => 'email', 'body_text' => 'Hi', 'is_active' => false,
        ]);

        NotificationEvent::query()->create([
            'event_type' => 'CustomerCreated', 'channel' => 'email', 'template_id' => $template->id, 'is_enabled' => true,
        ]);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchNotificationsForEvent::class)->handle($event, $this->store);

        expect(Notification::query()->count())->toBe(0);
    });
});

it('routes the same event to multiple channels independently', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create();

        $emailTemplate = NotificationTemplate::query()->create(['name' => 'Email', 'channel' => 'email', 'body_text' => 'Email body']);
        $smsTemplate = NotificationTemplate::query()->create(['name' => 'SMS', 'channel' => 'sms', 'body_text' => 'SMS body']);

        NotificationEvent::query()->create(['event_type' => 'CustomerCreated', 'channel' => 'email', 'template_id' => $emailTemplate->id]);
        NotificationEvent::query()->create(['event_type' => 'CustomerCreated', 'channel' => 'sms', 'template_id' => $smsTemplate->id]);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, []);
        app(DispatchNotificationsForEvent::class)->handle($event, $this->store);

        expect(Notification::query()->where('event_type', 'CustomerCreated')->count())->toBe(2);
        expect(Notification::query()->where('channel', 'email')->exists())->toBeTrue();
        expect(Notification::query()->where('channel', 'sms')->exists())->toBeTrue();
    });
});
