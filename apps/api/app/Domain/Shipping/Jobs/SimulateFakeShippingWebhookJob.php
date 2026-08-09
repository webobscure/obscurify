<?php

namespace App\Domain\Shipping\Jobs;

use App\Domain\Shipping\Http\Controllers\ShippingWebhookController;
use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Backs the fake shipment control page's "simulate full delivery" action
 * (spec section 14): dispatched with a delay so it genuinely arrives
 * asynchronously when a queue worker is running, degrading to immediate
 * (still correct, just not visibly delayed) under the sync driver used in
 * tests — see FakeShipmentOutcomeController. Mirrors
 * SimulateFakePaymentWebhookJob exactly.
 */
final class SimulateFakeShippingWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $externalShipmentId,
        public readonly string $outcome,
    ) {}

    public function handle(FakeShippingProvider $provider, ShippingWebhookController $webhookController): void
    {
        $sim = $provider->simulateWebhookPayload($this->externalShipmentId, $this->outcome);

        $request = Request::create(
            '/api/v1/shipping/webhooks/fake',
            'POST',
            content: $sim['payload'],
        );
        $request->headers->set('X-Fake-Shipping-Signature', $sim['signature']);
        $request->headers->set('Content-Type', 'application/json');

        // See FakeShipmentOutcomeController::outcome() for why this uses
        // app()->call() rather than a direct method call.
        app()->call([$webhookController, 'handle'], ['request' => $request, 'provider' => FakeShippingProvider::CODE]);
    }
}
