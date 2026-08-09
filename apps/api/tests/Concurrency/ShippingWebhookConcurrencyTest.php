<?php

use App\Domain\Shipping\Application\ProcessShippingWebhook;
use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShippingWebhookEvent;
use App\Domain\Shipping\Models\TrackingEvent;
use App\Domain\Shipping\Support\TrackingWebhookEvent;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reference Provider Hardening (spec section 28): two genuinely
 * simultaneous deliveries of the exact same provider event must never
 * produce two tracking events or apply the transition twice — the claim/
 * poll mechanism in ProcessShippingWebhook is what this proves under a
 * real second database connection, not just a sequential retry.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    $this->externalShipmentId = 'fake_ship_'.(string) Str::ulid();

    app(TenantContext::class)->scope($this->store, function () {
        Shipment::factory()->create([
            'external_shipment_id' => $this->externalShipmentId,
            'status' => 'created',
        ]);
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets two simultaneous deliveries of the identical webhook event apply the transition and record the tracking event exactly once', function () {
    $eventId = (string) Str::ulid();
    $payload = json_encode([
        'event_id' => $eventId,
        'external_shipment_id' => $this->externalShipmentId,
        'event_type' => 'shipment.updated',
        'status' => 'in_transit',
        'description' => 'Concurrency test.',
        'location' => null,
        'timestamp' => now()->timestamp,
    ]);

    $deliver = function () use ($eventId, $payload) {
        $event = new TrackingWebhookEvent(
            eventId: $eventId,
            externalShipmentId: $this->externalShipmentId,
            eventType: 'shipment.updated',
            status: 'in_transit',
            description: 'Concurrency test.',
            location: null,
            occurredAt: now(),
        );

        app(ProcessShippingWebhook::class)->handle(FakeShippingProvider::CODE, $event, $payload);

        return true;
    };

    $results = runConcurrently([$deliver, $deliver]);

    expect(array_filter($results, fn ($r) => $r['ok']))->toHaveCount(2); // both calls succeed — the second is a no-op, not an error

    app(TenantContext::class)->scope($this->store, function () use ($eventId) {
        $shipment = Shipment::query()->where('external_shipment_id', $this->externalShipmentId)->firstOrFail();
        expect($shipment->status->value)->toBe('in_transit');

        expect(TrackingEvent::query()->where('shipment_id', $shipment->id)->count())->toBe(1);
        expect(ShippingWebhookEvent::withoutGlobalScopes()->where('external_event_id', $eventId)->count())->toBe(1);
    });
});
