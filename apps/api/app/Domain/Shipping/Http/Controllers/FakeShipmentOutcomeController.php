<?php

namespace App\Domain\Shipping\Http\Controllers;

use App\Domain\Shipping\Exceptions\InvalidShippingWebhookSignatureException;
use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Jobs\SimulateFakeShippingWebhookJob;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Stores\Models\Store;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Backs the dev/test-only fake shipment control page (spec section 16).
 * Not reachable at all unless `commerce.shipping.fake.enabled` — both the
 * routes (routes/api.php) and this controller check it, same discipline as
 * FakePaymentOutcomeController (spec section 32: "guard via environment/
 * config, not only UI hiding").
 *
 * No auth, no tenant middleware — this simulates what an anonymous
 * external carrier (webhook sender) would do, and must resolve everything
 * from the (provider, external_shipment_id) pair rather than any ambient
 * session. Every state-changing action here goes through the *real*
 * signed webhook endpoint (spec section 11) — nothing in this controller
 * ever writes to the Shipment model directly.
 */
final class FakeShipmentOutcomeController extends Controller
{
    private const array OUTCOMES = ['accepted', 'in_transit', 'out_for_delivery', 'delivered', 'delivery_exception', 'failed', 'cancelled'];

    /**
     * Richer than a bare status (spec section 16): order number,
     * fulfillment id, destination, current state — everything the control
     * page needs to render without a second round trip.
     */
    public function show(string $externalShipmentId): JsonResponse
    {
        $this->assertEnabled();

        $shipment = Shipment::withoutGlobalScopes()
            ->where('provider', FakeShippingProvider::CODE)
            ->where('external_shipment_id', $externalShipmentId)
            ->firstOrFail();

        $store = Store::query()->findOrFail($shipment->store_id);

        // No tenant middleware runs on this route (it's an anonymous
        // dev-control endpoint, not an authenticated admin one) — order/
        // fulfillment/address are all BelongsToTenant, so their relation
        // queries need an active TenantContext the same way
        // ProcessShippingWebhook establishes one for the real webhook
        // handler, not a scope bypass on every nested relation.
        return app(TenantContext::class)->scope($store, function () use ($shipment) {
            $shipment->load(['order.shippingAddress', 'fulfillment', 'trackingEvents']);
            $shippingAddress = $shipment->order?->shippingAddress;

            return response()->json(['data' => [
                'shipment_id' => $shipment->id,
                'status' => $shipment->status->value,
                'tracking_number' => $shipment->tracking_number,
                'order_number' => $shipment->order?->number,
                'order_id' => $shipment->order_id,
                'fulfillment_id' => $shipment->fulfillment_id,
                'destination' => $shippingAddress === null ? null : [
                    'city' => $shippingAddress->city,
                    'country_code' => $shippingAddress->country_code,
                    'postal_code' => $shippingAddress->postal_code,
                ],
                'tracking_events' => $shipment->trackingEvents->map(fn ($event) => [
                    'status' => $event->status->value,
                    'description' => $event->description,
                    'occurred_at' => $event->occurred_at,
                ]),
            ]]);
        });
    }

    /**
     * Drives one lifecycle transition through the real signed webhook
     * endpoint. `event_id`/`timestamp` are overridable so the same action
     * covers the "send duplicate webhook" (spec section 12 — resubmit the
     * same event_id) and "send out-of-order/stale webhook" (spec section
     * 13/14 — an old timestamp, or a status regression) developer
     * actions, not just the happy-path lifecycle buttons. `delayed=true`
     * dispatches through the queue instead of processing synchronously
     * (spec section 14).
     */
    public function outcome(Request $request, string $externalShipmentId, FakeShippingProvider $provider, ShippingWebhookController $webhookController): JsonResponse
    {
        $this->assertEnabled();

        $data = $request->validate([
            'outcome' => ['required', 'string', Rule::in(self::OUTCOMES)],
            'event_id' => ['sometimes', 'nullable', 'string'],
            'timestamp' => ['sometimes', 'nullable', 'integer'],
            'delayed' => ['sometimes', 'boolean'],
        ]);

        Shipment::withoutGlobalScopes()
            ->where('provider', FakeShippingProvider::CODE)
            ->where('external_shipment_id', $externalShipmentId)
            ->firstOrFail();

        if ($data['delayed'] ?? false) {
            $delaySeconds = (int) config("commerce.shipping.fake.delayed_lifecycle.{$data['outcome']}_delay_seconds", 10);

            SimulateFakeShippingWebhookJob::dispatch($externalShipmentId, $data['outcome'])
                ->delay(now()->addSeconds($delaySeconds));

            return response()->json(['data' => ['queued' => true, 'delay_seconds' => $delaySeconds]]);
        }

        $eventId = $data['event_id'] ?? (string) Str::ulid();

        $sim = $provider->simulateWebhookPayload($externalShipmentId, $data['outcome'], $eventId, $data['timestamp'] ?? null);

        $signedRequest = Request::create(
            '/api/v1/shipping/webhooks/fake',
            'POST',
            content: $sim['payload'],
        );
        $signedRequest->headers->set('X-Fake-Shipping-Signature', $sim['signature']);
        $signedRequest->headers->set('Content-Type', 'application/json');

        // A direct method call skips the router's automatic dependency
        // injection for the controller's other (non-route) parameters —
        // app()->call() resolves them the same way the router would, same
        // pattern as FakePaymentOutcomeController::outcome().
        app()->call([$webhookController, 'handle'], ['request' => $signedRequest, 'provider' => FakeShippingProvider::CODE]);

        return response()->json(['data' => ['processed' => true, 'event_id' => $eventId]]);
    }

    /**
     * Developer action (spec section 15/16): builds a structurally valid
     * outcome payload but signs it with a deliberately wrong signature,
     * sent through the real webhook endpoint — proves the endpoint really
     * rejects it (403), not just that the fake control page trusts a
     * signature it computed itself.
     */
    public function invalidSignature(Request $request, string $externalShipmentId): JsonResponse
    {
        $this->assertEnabled();

        $data = $request->validate([
            'outcome' => ['required', 'string', Rule::in(self::OUTCOMES)],
        ]);

        Shipment::withoutGlobalScopes()
            ->where('provider', FakeShippingProvider::CODE)
            ->where('external_shipment_id', $externalShipmentId)
            ->firstOrFail();

        $payload = json_encode([
            'event_id' => (string) Str::ulid(),
            'external_shipment_id' => $externalShipmentId,
            'event_type' => 'shipment.updated',
            'status' => $data['outcome'],
            'description' => 'Invalid-signature dev test.',
            'location' => null,
            'timestamp' => now()->timestamp,
        ], JSON_THROW_ON_ERROR);

        $signedRequest = Request::create('/api/v1/shipping/webhooks/fake', 'POST', content: $payload);
        $signedRequest->headers->set('X-Fake-Shipping-Signature', 'not-a-real-signature');
        $signedRequest->headers->set('Content-Type', 'application/json');

        $webhookController = app(ShippingWebhookController::class);

        try {
            app()->call([$webhookController, 'handle'], ['request' => $signedRequest, 'provider' => FakeShippingProvider::CODE]);
        } catch (InvalidShippingWebhookSignatureException) {
            return response()->json(['data' => ['rejected' => true, 'reason' => 'invalid_webhook_signature']]);
        }

        // Should be unreachable — the endpoint is expected to reject this.
        return response()->json(['data' => ['rejected' => false]], 500);
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) config('commerce.shipping.fake.enabled'), 404);
    }
}
