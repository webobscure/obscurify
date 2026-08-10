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

    /**
     * Generates an external refund id — never marks anything refunded
     * here, mirrors createPayment()'s own "hand back an id, resolve the
     * outcome later via webhook" shape.
     */
    public function createRefund(Payment $payment, int $amount): string
    {
        return 'fake_refund_'.(string) Str::ulid();
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = $request->header(self::SIGNATURE_HEADER);

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($request->getContent()), $signature);
    }

    /**
     * Shared by both payment and refund payloads (spec section 7: "Use
     * same webhook pipeline as payments") — a refund payload carries
     * `external_refund_id` instead of `external_payment_id`; which one is
     * required is decided by the `event_type` prefix, not by trying both.
     */
    public function parseWebhook(Request $request): WebhookEvent
    {
        $data = json_decode($request->getContent(), true);

        if (! is_array($data)) {
            throw MalformedWebhookPayloadException::make('body is not a JSON object.');
        }

        foreach (['event_id', 'event_type', 'status', 'amount', 'currency', 'timestamp'] as $field) {
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

        $eventType = (string) $data['event_type'];
        $isRefundEvent = str_starts_with($eventType, 'refund.');
        $idField = $isRefundEvent ? 'external_refund_id' : 'external_payment_id';

        if (! array_key_exists($idField, $data) || ! is_string($data[$idField]) || $data[$idField] === '') {
            throw MalformedWebhookPayloadException::make("missing field \"{$idField}\".");
        }

        return new WebhookEvent(
            eventId: (string) $data['event_id'],
            externalPaymentId: $isRefundEvent ? null : $data[$idField],
            eventType: $eventType,
            status: (string) $data['status'],
            amount: (int) $data['amount'],
            currency: (string) $data['currency'],
            occurredAt: Carbon::createFromTimestamp((int) $data['timestamp']),
            externalRefundId: $isRefundEvent ? $data[$idField] : null,
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

    /**
     * Refund counterpart to simulateWebhookPayload() — same dev/test-only
     * reasoning, same signing, a different (smaller) status vocabulary:
     * a refund only ever resolves to succeeded or failed (spec section
     * 12), never "pending"/"cancelled" the way a payment page visit can.
     *
     * @return array{payload: string, signature: string}
     */
    public function simulateRefundWebhookPayload(string $externalRefundId, string $outcome, int $amount, string $currency): array
    {
        $status = match ($outcome) {
            'success' => 'succeeded',
            'failure' => 'failed',
            default => throw new InvalidArgumentException("Unknown fake refund outcome \"{$outcome}\"."),
        };

        $payload = [
            'event_id' => (string) Str::ulid(),
            'external_refund_id' => $externalRefundId,
            'event_type' => 'refund.updated',
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
