<?php

use App\Domain\Webhooks\Enums\WebhookDeliveryStatus;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Domain\Webhooks\Models\WebhookSubscription;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    $this->subscriptionB = app(TenantContext::class)->scope($this->storeB, fn () => WebhookSubscription::factory()->create());
});

it('creates a webhook subscription and returns the secret only once', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/webhook-subscriptions', [
        'name' => 'Order sync',
        'target_url' => 'https://example.test/hooks/orders',
        'event_types' => ['OrderCreated', 'OrderFinancialStatusChanged'],
    ], tenantHeader($this->storeA))->assertCreated();

    expect($response->json('data.name'))->toBe('Order sync')
        ->and($response->json('data.secret'))->toBeString()
        ->and($response->json('data.secret'))->not->toBeEmpty();

    $show = $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/webhook-subscriptions/{$response->json('data.id')}",
        tenantHeader($this->storeA),
    )->assertOk();

    expect($show->json('data'))->not->toHaveKey('secret');
});

it('lists and updates a webhook subscription', function () {
    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/webhook-subscriptions', [
        'name' => 'Fulfillment sync',
        'target_url' => 'https://example.test/hooks/fulfillment',
        'event_types' => ['FulfillmentCompleted'],
    ], tenantHeader($this->storeA))->assertCreated();

    $index = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/webhook-subscriptions', tenantHeader($this->storeA))->assertOk();
    expect(collect($index->json('data'))->pluck('name')->all())->toBe(['Fulfillment sync']);

    $updated = $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/webhook-subscriptions/{$created->json('data.id')}", [
        'status' => 'inactive',
    ], tenantHeader($this->storeA))->assertOk();

    expect($updated->json('data.status'))->toBe('inactive');
});

it('never lets Store A read, update, or list deliveries for a Store B subscription', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/webhook-subscriptions/{$this->subscriptionB->id}",
        tenantHeader($this->storeA),
    )->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/webhook-subscriptions/{$this->subscriptionB->id}",
        ['status' => 'inactive'],
        tenantHeader($this->storeA),
    )->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/webhook-subscriptions/{$this->subscriptionB->id}/deliveries",
        tenantHeader($this->storeA),
    )->assertNotFound();

    $deliveryB = app(TenantContext::class)->scope($this->storeB, fn () => WebhookDelivery::query()->create([
        'webhook_subscription_id' => $this->subscriptionB->id,
        'outbox_event_id' => (string) Str::ulid(),
        'event_type' => 'OrderCreated',
        'status' => WebhookDeliveryStatus::Failed->value,
        'attempt_count' => 1,
    ]));

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/webhook-deliveries/{$deliveryB->id}/retry",
        [],
        tenantHeader($this->storeA),
    )->assertNotFound();
});

it('lists deliveries for a subscription and retries a failed one', function () {
    Http::fake(['https://example.test/*' => Http::response([], 200)]);

    [$subscription, $delivery] = app(TenantContext::class)->scope($this->storeA, function () {
        $subscription = WebhookSubscription::factory()->create();
        $event = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', (string) Str::ulid(), []);
        $delivery = WebhookDelivery::query()->create([
            'webhook_subscription_id' => $subscription->id,
            'outbox_event_id' => $event->id,
            'event_type' => 'OrderCreated',
            'status' => WebhookDeliveryStatus::Failed->value,
            'attempt_count' => 1,
            'response_code' => 500,
        ]);

        return [$subscription, $delivery];
    });

    $index = $this->actingAs($this->userA, 'sanctum')->getJson(
        "/api/v1/webhook-subscriptions/{$subscription->id}/deliveries",
        tenantHeader($this->storeA),
    )->assertOk();
    expect($index->json('data'))->toHaveCount(1);

    $retried = $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/webhook-deliveries/{$delivery->id}/retry",
        [],
        tenantHeader($this->storeA),
    )->assertOk();

    // The sync queue driver in tests runs DeliverWebhookJob inline, so a
    // retry against the now-faked-200 target lands as succeeded, not
    // just reset to pending.
    expect($retried->json('data.status'))->toBe('succeeded');
});

it('rejects retrying a delivery that never failed', function () {
    $subscription = app(TenantContext::class)->scope($this->storeA, fn () => WebhookSubscription::factory()->create());

    $delivery = app(TenantContext::class)->scope($this->storeA, fn () => WebhookDelivery::query()->create([
        'webhook_subscription_id' => $subscription->id,
        'outbox_event_id' => (string) Str::ulid(),
        'event_type' => 'OrderCreated',
        'status' => WebhookDeliveryStatus::Succeeded->value,
        'attempt_count' => 1,
        'response_code' => 200,
        'delivered_at' => now(),
    ]));

    $this->actingAs($this->userA, 'sanctum')->postJson(
        "/api/v1/webhook-deliveries/{$delivery->id}/retry",
        [],
        tenantHeader($this->storeA),
    )->assertStatus(422);
});
