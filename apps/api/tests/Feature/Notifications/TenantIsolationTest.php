<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Application\EnsureDefaultNotificationSetup;
use App\Domain\Notifications\Application\NotificationDispatcher;
use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Enums\NotificationTriggerSource;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Models\NotificationProvider;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Support\NotificationDispatchRequest;
use App\Domain\Notifications\Support\NotificationRecipientInput;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    app(TenantContext::class)->scope($this->storeA, function () {
        app(EnsureDefaultNotificationSetup::class)->handle($this->storeA);
    });
    app(TenantContext::class)->scope($this->storeB, function () {
        app(EnsureDefaultNotificationSetup::class)->handle($this->storeB);
    });

    $this->templateA = app(TenantContext::class)->scope($this->storeA, fn () => NotificationTemplate::query()->create([
        'name' => 'A template', 'channel' => 'email', 'body_text' => 'Hi',
    ]));

    $this->customerA = app(TenantContext::class)->scope($this->storeA, fn () => Customer::factory()->create());

    $this->notificationA = app(TenantContext::class)->scope($this->storeA, fn () => app(NotificationDispatcher::class)->dispatch($this->storeA, new NotificationDispatchRequest(
        channel: NotificationChannelType::Email,
        triggeredBy: NotificationTriggerSource::Admin,
        recipients: [NotificationRecipientInput::customer($this->customerA->id)],
        bodyText: 'Hello A',
    )));

    app(TenantContext::class)->scope($this->storeA, function () {
        NotificationPreference::query()->create(['customer_id' => $this->customerA->id, 'sms_enabled' => true]);
    });
});

it('never lists another store\'s notification templates', function () {
    $this->actingAs($this->userB, 'sanctum')
        ->getJson('/api/v1/notification-templates', tenantHeader($this->storeB))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('404s reading another store\'s notification template by id', function () {
    $this->actingAs($this->userB, 'sanctum')
        ->getJson("/api/v1/notification-templates/{$this->templateA->id}", tenantHeader($this->storeB))
        ->assertNotFound();
});

it('never lists another store\'s notifications or deliveries', function () {
    $this->actingAs($this->userB, 'sanctum')
        ->getJson('/api/v1/notifications', tenantHeader($this->storeB))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->userB, 'sanctum')
        ->getJson('/api/v1/notification-deliveries', tenantHeader($this->storeB))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('404s reading another store\'s notification by id', function () {
    $this->actingAs($this->userB, 'sanctum')
        ->getJson("/api/v1/notifications/{$this->notificationA->id}", tenantHeader($this->storeB))
        ->assertNotFound();
});

it('never lists another store\'s notification channels or providers', function () {
    $responseChannels = $this->actingAs($this->userB, 'sanctum')
        ->getJson('/api/v1/notification-channels', tenantHeader($this->storeB))
        ->assertOk();

    // Store B has its own 5 default channels (seeded above) — none of
    // them should reference store A's provider.
    foreach ($responseChannels->json('data') as $channel) {
        app(TenantContext::class)->scope($this->storeA, function () use ($channel) {
            expect(NotificationProvider::withoutGlobalScopes()->where('id', $channel['provider_id'])->where('store_id', $this->storeA->id)->exists())->toBeFalse();
        });
    }
});

it('does not leak one customer\'s notification preferences to a lookup under a different store', function () {
    $customerB = app(TenantContext::class)->scope($this->storeB, fn () => Customer::factory()->create());

    $this->actingAs($this->userB, 'sanctum')
        ->getJson("/api/v1/customers/{$customerB->id}/notification-preferences", tenantHeader($this->storeB))
        ->assertOk()
        ->assertJsonPath('data.sms_enabled', false); // store A's customer had sms_enabled=true; this is a different customer entirely
});
