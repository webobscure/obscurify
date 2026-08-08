<?php

namespace App\Domain\Payments\Http\Controllers;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Infrastructure\Providers\FakePaymentProvider;
use App\Domain\Payments\Jobs\SimulateFakePaymentWebhookJob;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Backs the dev/test-only fake payment page (spec sections 9-12). Not
 * reachable at all unless `payments.fake.enabled` — both the routes
 * (routes/api.php) and this controller check it, so a stale route cache
 * or direct instantiation can't bypass the guard (spec section 11: "Guard
 * via environment/config, not only UI hiding").
 *
 * No auth, no tenant middleware — same reasoning as the webhook endpoint:
 * this simulates what an anonymous external provider page/webhook would
 * do, and must resolve everything from the (provider, external_payment_id)
 * pair rather than any ambient session.
 */
final class FakePaymentOutcomeController extends Controller
{
    public function show(string $externalPaymentId): JsonResponse
    {
        $this->assertEnabled();

        $payment = Payment::withoutGlobalScopes()
            ->where('provider', FakePaymentProvider::CODE)
            ->where('external_payment_id', $externalPaymentId)
            ->firstOrFail();

        $order = Order::withoutGlobalScopes()->find($payment->order_id);

        return response()->json(['data' => [
            'payment_id' => $payment->id,
            'order_number' => $order?->number,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
        ]]);
    }

    public function outcome(Request $request, string $externalPaymentId, FakePaymentProvider $provider, PaymentWebhookController $webhookController): JsonResponse
    {
        $this->assertEnabled();

        $data = $request->validate([
            'outcome' => ['required', 'string', Rule::in(['success', 'failure', 'cancelled', 'pending', 'delayed_success'])],
        ]);

        $payment = Payment::withoutGlobalScopes()
            ->where('provider', FakePaymentProvider::CODE)
            ->where('external_payment_id', $externalPaymentId)
            ->firstOrFail();

        if ($data['outcome'] === 'delayed_success') {
            SimulateFakePaymentWebhookJob::dispatch($externalPaymentId, 'success', $payment->amount, $payment->currency)
                ->delay(now()->addSeconds(5));

            return response()->json(['data' => ['dispatched' => true, 'delay_seconds' => 5]]);
        }

        $sim = $provider->simulateWebhookPayload($externalPaymentId, $data['outcome'], $payment->amount, $payment->currency);

        $signedRequest = Request::create(
            '/api/v1/payments/webhooks/fake',
            'POST',
            content: $sim['payload'],
        );
        $signedRequest->headers->set('X-Fake-Signature', $sim['signature']);
        $signedRequest->headers->set('Content-Type', 'application/json');

        // A direct method call skips the router's automatic dependency
        // injection for the controller's other (non-route) parameters —
        // app()->call() resolves them the same way the router would.
        app()->call([$webhookController, 'handle'], ['request' => $signedRequest, 'provider' => FakePaymentProvider::CODE]);

        return response()->json(['data' => ['processed' => true]]);
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) config('payments.fake.enabled'), 404);
    }
}
