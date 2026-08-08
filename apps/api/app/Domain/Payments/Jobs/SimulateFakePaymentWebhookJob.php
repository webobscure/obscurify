<?php

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Http\Controllers\PaymentWebhookController;
use App\Domain\Payments\Infrastructure\Providers\FakePaymentProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Backs the fake payment page's "delayed success" outcome (spec section
 * 10): dispatched with a delay so it genuinely arrives asynchronously
 * when a queue worker is running, degrading to immediate (still correct,
 * just not visibly delayed) under the sync driver used in tests — see
 * FakePaymentOutcomeController.
 */
final class SimulateFakePaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $externalPaymentId,
        public readonly string $outcome,
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public function handle(FakePaymentProvider $provider, PaymentWebhookController $webhookController): void
    {
        $sim = $provider->simulateWebhookPayload($this->externalPaymentId, $this->outcome, $this->amount, $this->currency);

        $request = Request::create(
            '/api/v1/payments/webhooks/fake',
            'POST',
            content: $sim['payload'],
        );
        $request->headers->set('X-Fake-Signature', $sim['signature']);
        $request->headers->set('Content-Type', 'application/json');

        // See FakePaymentOutcomeController::outcome() for why this uses
        // app()->call() rather than a direct method call.
        app()->call([$webhookController, 'handle'], ['request' => $request, 'provider' => FakePaymentProvider::CODE]);
    }
}
