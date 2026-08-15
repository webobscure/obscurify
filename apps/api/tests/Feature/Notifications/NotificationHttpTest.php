<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Application\EnsureDefaultNotificationSetup;
use App\Domain\Notifications\Application\NotificationDispatcher;
use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Enums\NotificationTriggerSource;
use App\Domain\Notifications\Support\NotificationDispatchRequest;
use App\Domain\Notifications\Support\NotificationRecipientInput;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-notification-http.localhost';
    domainForStore($this->store, $this->host);
    $this->withoutMiddleware(ThrottleRequests::class);

    app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultNotificationSetup::class)->handle($this->store);
    });
});

it('creates, reads, updates, and deletes a notification template', function () {
    $created = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/notification-templates', [
        'name' => 'Order confirmation', 'channel' => 'email', 'subject' => 'Order #{{order.number}}', 'body_text' => 'Thanks!',
    ], tenantHeader($this->store))->assertCreated();

    $id = $created->json('data.id');

    $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/notification-templates/{$id}", tenantHeader($this->store))
        ->assertOk()->assertJsonPath('data.name', 'Order confirmation');

    $this->actingAs($this->user, 'sanctum')->patchJson("/api/v1/notification-templates/{$id}", ['is_active' => false], tenantHeader($this->store))
        ->assertOk()->assertJsonPath('data.is_active', false);

    $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/notification-templates/{$id}", [], tenantHeader($this->store))
        ->assertNoContent();

    $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/notification-templates/{$id}", tenantHeader($this->store))
        ->assertNotFound();
});

it('lists the seeded default fake provider and channels', function () {
    $providers = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/notification-providers', tenantHeader($this->store))->assertOk();
    expect($providers->json('data'))->toHaveCount(1);
    expect($providers->json('data.0.code'))->toBe('fake');

    $channels = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/notification-channels', tenantHeader($this->store))->assertOk();
    expect($channels->json('data'))->toHaveCount(5);
});

it('creates a second provider and reassigns a channel to it', function () {
    $provider = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/notification-providers', [
        'code' => 'smtp', 'name' => 'My SMTP', 'is_enabled' => true,
    ], tenantHeader($this->store))->assertCreated();

    $channels = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/notification-channels', tenantHeader($this->store))->assertOk();
    $emailChannel = collect($channels->json('data'))->firstWhere('channel', 'email');

    $this->actingAs($this->user, 'sanctum')->patchJson("/api/v1/notification-channels/{$emailChannel['id']}", [
        'provider_id' => $provider->json('data.id'),
    ], tenantHeader($this->store))->assertOk()->assertJsonPath('data.provider_id', $provider->json('data.id'));
});

it('creates a notification event routing rule and it round-trips', function () {
    $template = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/notification-templates', [
        'name' => 'Welcome', 'channel' => 'email', 'body_text' => 'Hi',
    ], tenantHeader($this->store))->assertCreated();

    $event = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/notification-events', [
        'event_type' => 'CustomerCreated', 'channel' => 'email', 'template_id' => $template->json('data.id'),
    ], tenantHeader($this->store))->assertCreated();

    $this->actingAs($this->user, 'sanctum')->patchJson("/api/v1/notification-events/{$event->json('data.id')}", ['is_enabled' => false], tenantHeader($this->store))
        ->assertOk()->assertJsonPath('data.is_enabled', false);

    $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/notification-events/{$event->json('data.id')}", [], tenantHeader($this->store))
        ->assertNoContent();
});

it('sends an ad-hoc admin-composed notification and it appears in the Notification Center', function () {
    $customerId = app(TenantContext::class)->scope($this->store, fn () => Customer::factory()->create()->id);

    $sent = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/notifications', [
        'channel' => 'email', 'customer_id' => $customerId, 'subject' => 'Hi', 'body_text' => 'Manual message',
    ], tenantHeader($this->store))->assertCreated();

    expect($sent->json('data.status'))->toBe('delivered');
    expect($sent->json('data.deliveries'))->toHaveCount(1);

    $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/notifications', tenantHeader($this->store))
        ->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/notifications/{$sent->json('data.id')}", tenantHeader($this->store))
        ->assertOk()->assertJsonPath('data.body_text', 'Manual message');
});

it('filters the delivery log by status and can manually retry a failed one', function () {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/notifications', [
        'channel' => 'email', 'address' => 'always-fails@fail.test', 'body_text' => 'Will fail',
    ], tenantHeader($this->store))->assertCreated();

    $failed = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/notification-deliveries?status=failed', tenantHeader($this->store))
        ->assertOk();
    expect($failed->json('data'))->toHaveCount(1);

    $deliveryId = $failed->json('data.0.id');
    $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notification-deliveries/{$deliveryId}/retry", [], tenantHeader($this->store))
        ->assertOk()->assertJsonPath('data.attempt_count', 2);
});

it('lists a customer\'s notification history and marks one read through the portal', function () {
    $registered = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'history-test@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();
    $token = $registered->json('access_token');
    $customerId = $registered->json('data.id');

    app(TenantContext::class)->scope($this->store, function () use ($customerId) {
        app(NotificationDispatcher::class)->dispatch($this->store, new NotificationDispatchRequest(
            channel: NotificationChannelType::InApp,
            triggeredBy: NotificationTriggerSource::Admin,
            recipients: [NotificationRecipientInput::customer($customerId)],
            bodyText: 'You have a new order update.',
        ));
    });

    $history = $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account/notifications'), authHeader($token))->assertOk();
    expect($history->json('data'))->toHaveCount(1);
    expect($history->json('data.0.read_at'))->toBeNull();

    $recipientId = $history->json('data.0.id');
    $this->patchJson(storefrontUrl($this->host, "/api/v1/storefront/account/notifications/{$recipientId}/read"), [], authHeader($token))
        ->assertOk()->assertJsonPath('data.read_at', fn ($value) => $value !== null);
});
