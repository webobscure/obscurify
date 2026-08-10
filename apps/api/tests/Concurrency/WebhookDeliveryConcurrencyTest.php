<?php

use App\Domain\Webhooks\Application\DispatchWebhooksForEvent;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Domain\Webhooks\Models\WebhookSubscription;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Verifies WebhookDelivery's unique (webhook_subscription_id,
 * outbox_event_id) constraint actually prevents a double fan-out under
 * real concurrent PostgreSQL connections — see ReservationConcurrencyTest
 * for the identical fork-based pattern this mirrors.
 */
beforeEach(function () {
    Http::fake(['https://example.test/*' => Http::response([], 200)]);

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    [$this->event, $this->subscription] = app(TenantContext::class)->scope($this->store, function () {
        $event = app(RecordOutboxEvent::class)->handle('OrderCreated', 'Order', (string) Str::ulid(), []);
        $subscription = WebhookSubscription::factory()->create(['event_types' => ['OrderCreated'], 'target_url' => 'https://example.test/hook']);

        return [$event, $subscription];
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('never fans a single event out twice to the same subscription under concurrent dispatch', function () {
    $dispatch = function () {
        return app(TenantContext::class)->scope($this->store, function () {
            return app(DispatchWebhooksForEvent::class)->handle($this->event);
        });
    };

    $results = runConcurrently([$dispatch, $dispatch]);

    expect(array_filter($results, fn ($r) => $r['ok']))->toHaveCount(2);

    // Exactly one of the two claimed the delivery (returned 1); the
    // other found it already claimed (returned 0) — never both, never
    // neither.
    $claims = array_map(fn ($r) => $r['value'], $results);
    sort($claims);
    expect($claims)->toBe([0, 1]);

    app(TenantContext::class)->scope($this->store, function () {
        expect(WebhookDelivery::query()->count())->toBe(1);
    });
});
