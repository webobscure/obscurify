<?php

use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShippingWebhookEvent;
use App\Domain\Shipping\Models\TrackingEvent;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Signs an arbitrary (possibly malformed/stale) payload exactly like
 * FakeShippingProvider would — mirrors signedFakeWebhookRequest() from
 * PaymentWebhookTest.
 */
function signedFakeShippingWebhookRequest(array $payload): TestResponse
{
    $raw = json_encode($payload);
    $signature = hash_hmac('sha256', $raw, (string) config('commerce.shipping.fake.secret'));

    return test()->call('POST', '/api/v1/shipping/webhooks/fake', [], [], [], [
        'HTTP_X-Fake-Shipping-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $raw);
}

/**
 * @return array<string, mixed>
 */
function fakeShippingWebhookPayload(string $externalShipmentId, string $status, ?int $timestamp = null): array
{
    return [
        'event_id' => (string) Str::ulid(),
        'external_shipment_id' => $externalShipmentId,
        'event_type' => 'shipment.updated',
        'status' => $status,
        'description' => "Simulated {$status}.",
        'location' => null,
        'timestamp' => $timestamp ?? now()->timestamp,
    ];
}

beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);

    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);

    $shipment = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/shipments", [
        'provider' => 'fake',
        'lines' => [['order_item_id' => $orderItemId, 'quantity' => 1]],
    ], tenantHeader($this->storeA))->assertCreated();

    $this->shipmentId = $shipment->json('data.id');
    $this->externalShipmentId = Str::after($shipment->json('data.tracking_url'), '/fake-shipments/');
});

it('moves a shipment to in_transit then delivered on valid signed webhooks, appending tracking events', function () {
    signedFakeShippingWebhookRequest(fakeShippingWebhookPayload($this->externalShipmentId, 'in_transit'))->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        $shipment = Shipment::query()->whereKey($this->shipmentId)->firstOrFail();
        expect($shipment->status->value)->toBe('in_transit')
            ->and($shipment->shipped_at)->not->toBeNull();
    });

    signedFakeShippingWebhookRequest(fakeShippingWebhookPayload($this->externalShipmentId, 'delivered'))->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        $shipment = Shipment::query()->whereKey($this->shipmentId)->firstOrFail();
        expect($shipment->status->value)->toBe('delivered')
            ->and($shipment->delivered_at)->not->toBeNull();

        expect(TrackingEvent::query()->where('shipment_id', $this->shipmentId)->pluck('status')->map(fn ($s) => $s->value)->all())
            ->toBe(['created', 'in_transit', 'delivered']);
    });
});

it('rejects a webhook with an invalid signature', function () {
    $raw = json_encode(fakeShippingWebhookPayload($this->externalShipmentId, 'in_transit'));

    $this->call('POST', '/api/v1/shipping/webhooks/fake', [], [], [], [
        'HTTP_X-Fake-Shipping-Signature' => 'not-the-real-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertStatus(403)->assertJsonPath('error', 'invalid_webhook_signature');

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Shipment::query()->whereKey($this->shipmentId)->firstOrFail()->status->value)->toBe('created'));
});

it('is idempotent under a genuinely duplicated webhook delivery: one transition, one tracking event, one event row', function () {
    $payload = fakeShippingWebhookPayload($this->externalShipmentId, 'in_transit');

    signedFakeShippingWebhookRequest($payload)->assertOk();
    signedFakeShippingWebhookRequest($payload)->assertOk();
    signedFakeShippingWebhookRequest($payload)->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () use ($payload) {
        $shipment = Shipment::query()->whereKey($this->shipmentId)->firstOrFail();
        expect($shipment->status->value)->toBe('in_transit')
            ->and(TrackingEvent::query()->where('shipment_id', $this->shipmentId)->count())->toBe(2); // created + in_transit

        expect(ShippingWebhookEvent::withoutGlobalScopes()->where('external_event_id', $payload['event_id'])->count())->toBe(1);
    });
});

it('rejects a webhook for an unknown shipment safely', function () {
    signedFakeShippingWebhookRequest(fakeShippingWebhookPayload('fake_ship_does_not_exist', 'in_transit'))
        ->assertStatus(404)
        ->assertJsonPath('error', 'unknown_shipment');
});

it('rejects an unknown provider', function () {
    $this->postJson('/api/v1/shipping/webhooks/dhl', ['anything' => true])->assertStatus(404);
});

it('rejects a malformed webhook payload', function () {
    $payload = ['event_id' => 'x', 'external_shipment_id' => $this->externalShipmentId];
    $raw = json_encode($payload);
    $signature = hash_hmac('sha256', $raw, (string) config('commerce.shipping.fake.secret'));

    $this->call('POST', '/api/v1/shipping/webhooks/fake', [], [], [], [
        'HTTP_X-Fake-Shipping-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertStatus(422)->assertJsonPath('error', 'malformed_webhook_payload');
});

it('rejects a webhook whose timestamp is outside the replay tolerance window', function () {
    $stale = now()->subSeconds((int) config('commerce.shipping.webhook.replay_tolerance_seconds') + 60)->timestamp;

    signedFakeShippingWebhookRequest(fakeShippingWebhookPayload($this->externalShipmentId, 'in_transit', timestamp: $stale))
        ->assertStatus(422)
        ->assertJsonPath('error', 'webhook_replay_rejected');
});

it('does not error on an out-of-order transition, and leaves the shipment in its current terminal state', function () {
    signedFakeShippingWebhookRequest(fakeShippingWebhookPayload($this->externalShipmentId, 'in_transit'))->assertOk();
    signedFakeShippingWebhookRequest(fakeShippingWebhookPayload($this->externalShipmentId, 'delivered'))->assertOk();
    // Out-of-order: an "in_transit" arriving after the shipment already
    // reached the terminal "delivered" state — same policy as Payments'
    // equivalent out-of-order case, documented in ProcessShippingWebhook:
    // rejected as a transition (delivered has no outgoing transitions),
    // but still safely accepted and recorded as a webhook event.
    signedFakeShippingWebhookRequest(fakeShippingWebhookPayload($this->externalShipmentId, 'in_transit'))->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Shipment::query()->whereKey($this->shipmentId)->firstOrFail()->status->value)->toBe('delivered')
            // All four deliveries are recorded (append-only ledger:
            // created, in_transit, delivered, late in_transit), but the
            // late one didn't drive a second status transition.
            ->and(TrackingEvent::query()->where('shipment_id', $this->shipmentId)->count())->toBe(4);
    });
});
