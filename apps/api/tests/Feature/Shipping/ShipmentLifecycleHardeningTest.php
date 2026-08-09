<?php

use App\Domain\Shipping\Jobs\SimulateFakeShippingWebhookJob;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShippingWebhookEvent;
use App\Domain\Shipping\Models\TrackingEvent;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Reference Provider Hardening: the richer async lifecycle (accepted,
 * out_for_delivery, delivery_exception) and the dev control endpoints
 * that drive it (duplicate/invalid-signature/delayed).
 */
beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);

    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);

    $completed = shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]])->assertOk();

    $this->shipmentId = $completed->json('data.shipments.0.id');
    $this->externalShipmentId = Str::after($completed->json('data.shipments.0.tracking_url'), '/fake-shipments/');
});

function outcomeRequest(string $externalShipmentId, array $data = []): TestResponse
{
    return test()->postJson("/api/v1/fake-shipments/{$externalShipmentId}/outcome", $data);
}

it('walks the full async lifecycle: created -> accepted -> in_transit -> out_for_delivery -> delivered', function () {
    outcomeRequest($this->externalShipmentId, ['outcome' => 'accepted'])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit'])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'out_for_delivery'])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'delivered'])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        $shipment = Shipment::query()->whereKey($this->shipmentId)->firstOrFail();
        expect($shipment->status->value)->toBe('delivered')
            ->and($shipment->shipped_at)->not->toBeNull()
            ->and($shipment->delivered_at)->not->toBeNull();

        expect(TrackingEvent::query()->where('shipment_id', $this->shipmentId)->pluck('status')->map(fn ($s) => $s->value)->all())
            ->toBe(['created', 'accepted', 'in_transit', 'out_for_delivery', 'delivered']);
    });
});

it('recovers from a delivery_exception back to out_for_delivery, then delivers', function () {
    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit'])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'out_for_delivery'])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'delivery_exception'])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Shipment::query()->whereKey($this->shipmentId)->firstOrFail()->status->value)->toBe('delivery_exception');
    });

    outcomeRequest($this->externalShipmentId, ['outcome' => 'out_for_delivery'])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'delivered'])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        $shipment = Shipment::query()->whereKey($this->shipmentId)->firstOrFail();
        expect($shipment->status->value)->toBe('delivered');

        // created + in_transit + out_for_delivery + delivery_exception + out_for_delivery + delivered
        expect(TrackingEvent::query()->where('shipment_id', $this->shipmentId)->count())->toBe(6);
    });
});

it('rejects an out-of-order regression (delivered then in_transit) as a state transition, but still records the tracking event', function () {
    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit'])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'delivered'])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit'])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Shipment::query()->whereKey($this->shipmentId)->firstOrFail()->status->value)->toBe('delivered')
            ->and(TrackingEvent::query()->where('shipment_id', $this->shipmentId)->count())->toBe(4); // created, in_transit, delivered, late in_transit
    });
});

it('is idempotent under a genuinely duplicated event_id sent through the dev control endpoint', function () {
    $eventId = (string) Str::ulid();

    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit', 'event_id' => $eventId])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit', 'event_id' => $eventId])->assertOk();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit', 'event_id' => $eventId])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () use ($eventId) {
        expect(TrackingEvent::query()->where('shipment_id', $this->shipmentId)->count())->toBe(2); // created + one in_transit
        expect(ShippingWebhookEvent::withoutGlobalScopes()->where('external_event_id', $eventId)->count())->toBe(1);
    });
});

it('rejects a webhook whose timestamp is outside the replay tolerance window, sent through the dev control endpoint', function () {
    $stale = now()->subSeconds((int) config('commerce.shipping.webhook.replay_tolerance_seconds') + 60)->timestamp;

    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit', 'timestamp' => $stale])
        ->assertStatus(422)->assertJsonPath('error', 'webhook_replay_rejected');
});

it('the invalid-signature dev action proves the real webhook endpoint actually rejects a bad signature', function () {
    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit'])->assertOk();

    $response = $this->postJson("/api/v1/fake-shipments/{$this->externalShipmentId}/invalid-signature", ['outcome' => 'delivered']);

    $response->assertOk()->assertJsonPath('data.rejected', true)->assertJsonPath('data.reason', 'invalid_webhook_signature');

    // Confirmed unaffected — a rejected signature never reaches the state
    // machine or the timeline.
    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Shipment::query()->whereKey($this->shipmentId)->firstOrFail()->status->value)->toBe('in_transit')
            ->and(TrackingEvent::query()->where('shipment_id', $this->shipmentId)->count())->toBe(2);
    });
});

it('dispatches a delayed queued job instead of processing synchronously when delayed=true', function () {
    Queue::fake();

    outcomeRequest($this->externalShipmentId, ['outcome' => 'accepted', 'delayed' => true])
        ->assertOk()->assertJsonPath('data.queued', true);

    Queue::assertPushed(SimulateFakeShippingWebhookJob::class, function ($job) {
        return $job->externalShipmentId === $this->externalShipmentId && $job->outcome === 'accepted';
    });

    // Nothing processed yet — the job was only queued, not run.
    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Shipment::query()->whereKey($this->shipmentId)->firstOrFail()->status->value)->toBe('created');
    });
});

it('the fake shipment control page (show) surfaces order/fulfillment context, not just status', function () {
    $response = $this->getJson("/api/v1/fake-shipments/{$this->externalShipmentId}");

    $response->assertOk()
        ->assertJsonPath('data.status', 'created')
        ->assertJsonPath('data.destination.country_code', 'US');

    expect($response->json('data.order_number'))->not->toBeNull()
        ->and($response->json('data.fulfillment_id'))->not->toBeNull();
});

it('the fake control endpoints 404 when the fake provider is disabled', function () {
    config(['commerce.shipping.fake.enabled' => false]);

    $this->getJson("/api/v1/fake-shipments/{$this->externalShipmentId}")->assertNotFound();
    outcomeRequest($this->externalShipmentId, ['outcome' => 'in_transit'])->assertNotFound();
});
