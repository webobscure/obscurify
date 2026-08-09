<?php

namespace App\Domain\Shipping\Http\Controllers;

use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Models\Shipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Backs the dev/test-only fake shipment control page (spec section 20).
 * Not reachable at all unless `commerce.shipping.fake.enabled` — both the
 * routes (routes/api.php) and this controller check it, same discipline as
 * FakePaymentOutcomeController (spec section 40: "guard via environment/
 * config, not only UI hiding").
 *
 * No auth, no tenant middleware — this simulates what an anonymous
 * external carrier webhook would do, and must resolve everything from the
 * (provider, external_shipment_id) pair rather than any ambient session.
 */
final class FakeShipmentOutcomeController extends Controller
{
    public function show(string $externalShipmentId): JsonResponse
    {
        $this->assertEnabled();

        $shipment = Shipment::withoutGlobalScopes()
            ->where('provider', FakeShippingProvider::CODE)
            ->where('external_shipment_id', $externalShipmentId)
            ->firstOrFail();

        return response()->json(['data' => [
            'shipment_id' => $shipment->id,
            'status' => $shipment->status->value,
            'tracking_number' => $shipment->tracking_number,
        ]]);
    }

    public function outcome(Request $request, string $externalShipmentId, FakeShippingProvider $provider, ShippingWebhookController $webhookController): JsonResponse
    {
        $this->assertEnabled();

        $data = $request->validate([
            'outcome' => ['required', 'string', Rule::in(['in_transit', 'delivered', 'failed', 'cancelled'])],
        ]);

        Shipment::withoutGlobalScopes()
            ->where('provider', FakeShippingProvider::CODE)
            ->where('external_shipment_id', $externalShipmentId)
            ->firstOrFail();

        $sim = $provider->simulateWebhookPayload($externalShipmentId, $data['outcome']);

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

        return response()->json(['data' => ['processed' => true]]);
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) config('commerce.shipping.fake.enabled'), 404);
    }
}
