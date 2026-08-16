<?php

namespace App\Domain\RussianCommerce\Support\Providers;

use App\Domain\RussianCommerce\Models\FiscalReceipt;
use App\Domain\RussianCommerce\Support\FiscalizationCallbackEvent;
use App\Domain\RussianCommerce\Support\FiscalizationProviderContract;
use App\Domain\RussianCommerce\Support\FiscalizationSubmissionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Makes no external HTTP requests (spec section 13) — mirrors
 * FakePaymentProvider's exact shape: submit now, resolve the outcome
 * later via a signed, async callback, never synchronously.
 *
 * "Delayed success" (spec section 13) is modeled as *when* the caller
 * chooses to POST the callback, not a different payload shape — a real
 * OFD provider's delay is entirely about timing, so
 * simulateCallbackPayload('delayed_success', ...) is byte-identical to
 * 'success'; a test simulating a delay simply waits (or advances time)
 * before invoking the callback endpoint, exactly like a real
 * integration would experience it.
 *
 * Only registered when `russian_commerce.fake_fiscalization.enabled` is
 * true — see RussianCommerceServiceProvider.
 */
final class FakeFiscalizationProvider implements FiscalizationProviderContract
{
    public const string CODE = 'fake';

    private const string SIGNATURE_HEADER = 'X-Fake-Fiscalization-Signature';

    public function code(): string
    {
        return self::CODE;
    }

    /**
     * Never marks the receipt fiscalized here — only ever through a
     * verified callback (ProcessFiscalizationCallback).
     */
    public function submitReceipt(FiscalReceipt $receipt): FiscalizationSubmissionResult
    {
        return new FiscalizationSubmissionResult(externalReceiptId: 'fake_receipt_'.(string) Str::ulid());
    }

    public function verifyCallback(Request $request): bool
    {
        $signature = $request->header(self::SIGNATURE_HEADER);

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($request->getContent()), $signature);
    }

    public function parseCallback(Request $request): FiscalizationCallbackEvent
    {
        $data = json_decode($request->getContent(), true);

        if (! is_array($data) || ! isset($data['external_receipt_id'], $data['status'])) {
            throw new InvalidArgumentException('Malformed fiscalization callback payload.');
        }

        return new FiscalizationCallbackEvent(
            externalReceiptId: (string) $data['external_receipt_id'],
            succeeded: $data['status'] === 'fiscalized',
            errorMessage: $data['status'] === 'failed' ? ($data['error_message'] ?? 'Fiscalization failed.') : null,
        );
    }

    /**
     * Dev/test-only harness — a real provider never exposes this, only
     * our own fiscalization test flow needs it (same reasoning as
     * FakePaymentProvider::simulateWebhookPayload).
     *
     * @return array{payload: string, signature: string}
     */
    public function simulateCallbackPayload(string $externalReceiptId, string $outcome): array
    {
        $status = match ($outcome) {
            'success', 'delayed_success' => 'fiscalized',
            'failure' => 'failed',
            default => throw new InvalidArgumentException("Unknown fake fiscalization outcome \"{$outcome}\"."),
        };

        $payload = array_filter([
            'external_receipt_id' => $externalReceiptId,
            'status' => $status,
            'error_message' => $status === 'failed' ? 'Simulated fiscalization failure.' : null,
        ], fn ($value) => $value !== null);

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        return ['payload' => $raw, 'signature' => $this->sign($raw)];
    }

    private function sign(string $rawPayload): string
    {
        return hash_hmac('sha256', $rawPayload, config('russian_commerce.fake_fiscalization.secret'));
    }
}
