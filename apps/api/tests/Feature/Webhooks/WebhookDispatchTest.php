<?php

use App\Domain\Webhooks\Application\DispatchWebhooksForEvent;
use App\Domain\Webhooks\Enums\WebhookDeliveryStatus;
use App\Domain\Webhooks\Enums\WebhookSubscriptionStatus;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Domain\Webhooks\Models\WebhookSubscription;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('delivers a matching event to an active subscription, HMAC-signed', function () {
    Http::fake(['https://example.test/*' => Http::response(['ok' => true], 200)]);

    [$event, $subscription] = app(TenantContext::class)->scope($this->store, function () {
        $event = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', (string) Str::ulid(), ['order_id' => 'abc']);
        $subscription = WebhookSubscription::factory()->create(['event_types' => ['OrderCreated'], 'target_url' => 'https://example.test/hook']);

        return [$event, $subscription];
    });

    app(TenantContext::class)->scope($this->store, function () use ($event) {
        $dispatched = app(DispatchWebhooksForEvent::class)->handle($event);
        expect($dispatched)->toBe(1);
    });

    Http::assertSent(function ($request) use ($subscription) {
        $expectedSignature = hash_hmac('sha256', $request->body(), $subscription->fresh()->secret);

        return $request->url() === 'https://example.test/hook'
            && $request->hasHeader('X-Obscurify-Webhook-Signature', $expectedSignature)
            && $request->hasHeader('X-Obscurify-Webhook-Event', 'OrderCreated');
    });

    app(TenantContext::class)->scope($this->store, function () {
        $delivery = WebhookDelivery::query()->firstOrFail();
        expect($delivery->status)->toBe(WebhookDeliveryStatus::Succeeded)
            ->and($delivery->response_code)->toBe(200)
            ->and($delivery->delivered_at)->not->toBeNull();
    });
});

it('never delivers to a subscription whose event_types do not match', function () {
    Http::fake();

    [, $event] = app(TenantContext::class)->scope($this->store, function () {
        WebhookSubscription::factory()->create(['event_types' => ['SomethingElse']]);
        $event = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', (string) Str::ulid(), []);

        return [null, $event];
    });

    app(TenantContext::class)->scope($this->store, function () use ($event) {
        expect(app(DispatchWebhooksForEvent::class)->handle($event))->toBe(0);
    });

    Http::assertNothingSent();
});

it('matches a wildcard subscription against any event type', function () {
    Http::fake(['https://example.test/*' => Http::response([], 200)]);

    $event = app(TenantContext::class)->scope($this->store, function () {
        WebhookSubscription::factory()->create(['event_types' => ['*'], 'target_url' => 'https://example.test/hook']);

        return app(RecordOutboxEvent::class)->handle('AnyRandomEvent', 'Order', (string) Str::ulid(), []);
    });

    app(TenantContext::class)->scope($this->store, function () use ($event) {
        expect(app(DispatchWebhooksForEvent::class)->handle($event))->toBe(1);
    });
});

it('never delivers to an inactive subscription', function () {
    Http::fake();

    $event = app(TenantContext::class)->scope($this->store, function () {
        WebhookSubscription::factory()->create(['event_types' => ['OrderCreated'], 'status' => WebhookSubscriptionStatus::Inactive]);

        return app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', (string) Str::ulid(), []);
    });

    app(TenantContext::class)->scope($this->store, function () use ($event) {
        expect(app(DispatchWebhooksForEvent::class)->handle($event))->toBe(0);
    });

    Http::assertNothingSent();
});

it('records a failed delivery when the target responds with an error, without exhausting after one attempt', function () {
    Http::fake(['https://example.test/*' => Http::response('nope', 500)]);

    $event = app(TenantContext::class)->scope($this->store, function () {
        WebhookSubscription::factory()->create(['event_types' => ['OrderCreated'], 'target_url' => 'https://example.test/hook']);

        return app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', (string) Str::ulid(), []);
    });

    app(TenantContext::class)->scope($this->store, function () use ($event) {
        app(DispatchWebhooksForEvent::class)->handle($event);
    });

    app(TenantContext::class)->scope($this->store, function () {
        $delivery = WebhookDelivery::query()->firstOrFail();
        expect($delivery->status)->toBe(WebhookDeliveryStatus::Failed)
            ->and($delivery->response_code)->toBe(500)
            ->and($delivery->attempt_count)->toBe(1);
    });
});

it('dispatches webhooks from the outbox:process command and marks the event processed', function () {
    Http::fake(['https://example.test/*' => Http::response([], 200)]);

    app(TenantContext::class)->scope($this->store, function () {
        WebhookSubscription::factory()->create(['event_types' => ['OrderCreated'], 'target_url' => 'https://example.test/hook']);
        app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', (string) Str::ulid(), []);
    });

    Artisan::call('outbox:process');

    app(TenantContext::class)->scope($this->store, function () {
        expect(OutboxEvent::query()->firstOrFail()->processed_at)->not->toBeNull();
        expect(WebhookDelivery::query()->count())->toBe(1);
    });

    Http::assertSentCount(1);
});
