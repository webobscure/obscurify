<?php

namespace App\Domain\Payments\Infrastructure\Providers;

use App\Domain\Payments\Contracts\PaymentProviderContract;
use App\Domain\Payments\Exceptions\MalformedWebhookPayloadException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\PaymentInitiationResult;
use App\Domain\Payments\Support\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Makes no external HTTP requests — everything it does is local and
 * deterministic. Behaves conceptually like a real redirect-based provider
 * (create a payment, hand back a redirect URL, resolve the outcome later
 * via an async, signed webhook) so the rest of the Payments module never
 * needs to know it isn't real.
 *
 * Only registered when `payments.fake.enabled` is true — see
 * PaymentServiceProvider.
 */
final class FakePaymentProvider implements PaymentProviderContract
{
    public const string CODE = 'fake';

    private const string SIGNATURE_HEADER = 'X-Fake-Signature';

    public function code(): string
    {
        return self::CODE;
    }

    /**
     * Generates an external id and a redirect URL to our own dev-only
     * fake payment page — never marks the Payment paid here. The literal
     * shape matches spec section 9's example.
     */
    public function createPayment(Payment $payment): PaymentInitiationResult
    {
        $externalPaymentId = 'fake_'.(string) Str::ulid();

        return new PaymentInitiationResult(
            externalPaymentId: $externalPaymentId,
            redirectUrl: "/fake-payments/{$externalPaymentId}",
        );
    }

    /**
     * Not exercised by this milestone's flow (the fake provider goes
     * straight from `processing` to a terminal status via webhook, see
     * ProcessPaymentWebhook) — implemented for contract completeness and
     * for a near-future provider that does need an explicit capture step.
     */
    public function capturePayment(Payment $payment, ?int $amount = null): void
    {
        // Intentionally a no-op for the fake provider: nothing external
        // to call, and nothing in this milestone drives this path.
    }

    public function cancelPayment(Payment $payment): void
    {
        // Same as capturePayment() — not exercised this milestone.
    }

    public function refundPayment(Payment $payment, int $amount): void
    {
        // Refunds are explicitly out of scope this milestone (spec
        // section 35) — present only so the contract shape is real.
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = $request->header(self::SIGNATURE_HEADER);

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($request->getContent()), $signature);
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $data = json_decode($request->getContent(), true);

        if (! is_array($data)) {
            throw MalformedWebhookPayloadException::make('body is not a JSON object.');
        }

        foreach (['event_id', 'external_payment_id', 'event_type', 'status', 'amount', 'currency', 'timestamp'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw MalformedWebhookPayloadException::make("missing field \"{$field}\".");
            }
        }

        if (! is_int($data['amount']) && ! is_numeric($data['amount'])) {
            throw MalformedWebhookPayloadException::make('"amount" is not numeric.');
        }

        if (! is_numeric($data['timestamp'])) {
            throw MalformedWebhookPayloadException::make('"timestamp" is not numeric.');
        }

        return new WebhookEvent(
            eventId: (string) $data['event_id'],
            externalPaymentId: (string) $data['external_payment_id'],
            eventType: (string) $data['event_type'],
            status: (string) $data['status'],
            amount: (int) $data['amount'],
            currency: (string) $data['currency'],
            occurredAt: Carbon::createFromTimestamp((int) $data['timestamp']),
        );
    }

    /**
     * Dev/test-only harness: builds and signs a webhook payload for a
     * simulated outcome. A real provider would never expose this — only
     * our own fake payment page needs it — which is exactly why it lives
     * here and not on PaymentProviderContract.
     *
     * @return array{payload: string, signature: string}
     */
    public function simulateWebhookPayload(string $externalPaymentId, string $outcome, int $amount, string $currency): array
    {
        $status = match ($outcome) {
            'success', 'delayed_success' => 'succeeded',
            'failure' => 'failed',
            'cancelled' => 'cancelled',
            'pending' => 'pending',
            default => throw new InvalidArgumentException("Unknown fake payment outcome \"{$outcome}\"."),
        };

        $payload = [
            'event_id' => (string) Str::ulid(),
            'external_payment_id' => $externalPaymentId,
            'event_type' => 'payment.updated',
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'timestamp' => now()->timestamp,
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        return ['payload' => $raw, 'signature' => $this->sign($raw)];
    }

    private function sign(string $rawPayload): string
    {
        return hash_hmac('sha256', $rawPayload, config('payments.fake.secret'));
    }
}
