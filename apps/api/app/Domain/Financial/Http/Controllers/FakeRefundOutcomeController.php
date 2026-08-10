<?php

namespace App\Domain\Financial\Http\Controllers;

use App\Domain\Financial\Models\Refund;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Http\Controllers\PaymentWebhookController;
use App\Domain\Payments\Infrastructure\Providers\FakePaymentProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Backs the dev/test-only fake refund control page — mirrors
 * FakePaymentOutcomeController exactly, same double-guard discipline
 * (route registration + this controller both check
 * `payments.fake.enabled`; no separate refund-specific flag, since this
 * is still the same fake provider). No auth, no tenant middleware: this
 * simulates what an anonymous external provider webhook would do, and
 * resolves everything from (provider, provider_reference) rather than
 * any ambient session.
 */
final class FakeRefundOutcomeController extends Controller
{
    public function show(string $externalRefundId): JsonResponse
    {
        $this->assertEnabled();

        $refund = Refund::withoutGlobalScopes()
            ->where('provider', FakePaymentProvider::CODE)
            ->where('provider_reference', $externalRefundId)
            ->firstOrFail();

        $order = Order::withoutGlobalScopes()->find($refund->order_id);

        return response()->json(['data' => [
            'refund_id' => $refund->id,
            'order_number' => $order?->number,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'status' => $refund->status->value,
        ]]);
    }

    public function outcome(Request $request, string $externalRefundId, FakePaymentProvider $provider, PaymentWebhookController $webhookController): JsonResponse
    {
        $this->assertEnabled();

        $data = $request->validate([
            'outcome' => ['required', 'string', Rule::in(['success', 'failure'])],
        ]);

        $refund = Refund::withoutGlobalScopes()
            ->where('provider', FakePaymentProvider::CODE)
            ->where('provider_reference', $externalRefundId)
            ->firstOrFail();

        $sim = $provider->simulateRefundWebhookPayload($externalRefundId, $data['outcome'], $refund->amount, $refund->currency);

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
