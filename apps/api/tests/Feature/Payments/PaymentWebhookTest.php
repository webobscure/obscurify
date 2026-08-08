<?php

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhookEvent;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Signs an arbitrary (possibly malformed/stale) payload exactly like
 * FakePaymentProvider would, without needing access to its private
 * signing method — real providers would similarly be testable purely
 * from their public signing contract.
 */
function signedFakeWebhookRequest(array $payload): TestResponse
{
    $raw = json_encode($payload);
    $signature = hash_hmac('sha256', $raw, (string) config('payments.fake.secret'));

    return test()->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], [
        'HTTP_X-Fake-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $raw);
}

/**
 * @return array<string, mixed>
 */
function fakeWebhookPayload(string $externalPaymentId, string $status, int $amount = 1000, string $currency = 'RUB', ?int $timestamp = null): array
{
    return [
        'event_id' => (string) Str::ulid(),
        'external_payment_id' => $externalPaymentId,
        'event_type' => 'payment.updated',
        'status' => $status,
        'amount' => $amount,
        'currency' => $currency,
        'timestamp' => $timestamp ?? now()->timestamp,
    ];
}

beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);

    ['order_id' => $this->orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    $payment = $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$this->orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'webhook-test-key'],
    )->assertCreated();

    $this->externalPaymentId = Str::after($payment->json('data.redirect_url'), '/fake-payments/');
});

it('marks the payment paid and the order financial_status paid on a valid signed success webhook', function () {
    signedFakeWebhookRequest(fakeWebhookPayload($this->externalPaymentId, 'succeeded'))->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Payment::query()->where('external_payment_id', $this->externalPaymentId)->firstOrFail()->status->value)->toBe('paid')
            ->and(Order::query()->whereKey($this->orderId)->firstOrFail()->financial_status->value)->toBe('paid');
    });
});

it('does not mark the order paid on a failed webhook', function () {
    signedFakeWebhookRequest(fakeWebhookPayload($this->externalPaymentId, 'failed'))->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Payment::query()->where('external_payment_id', $this->externalPaymentId)->firstOrFail()->status->value)->toBe('failed')
            ->and(Order::query()->whereKey($this->orderId)->firstOrFail()->financial_status->value)->toBe('pending');
    });
});

it('handles a cancelled webhook, leaving the order pending', function () {
    signedFakeWebhookRequest(fakeWebhookPayload($this->externalPaymentId, 'cancelled'))->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Payment::query()->where('external_payment_id', $this->externalPaymentId)->firstOrFail()->status->value)->toBe('cancelled')
            ->and(Order::query()->whereKey($this->orderId)->firstOrFail()->financial_status->value)->toBe('pending');
    });
});

it('leaves the payment processing on a pending webhook ping', function () {
    signedFakeWebhookRequest(fakeWebhookPayload($this->externalPaymentId, 'pending'))->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Payment::query()->where('external_payment_id', $this->externalPaymentId)->firstOrFail()->status->value)->toBe('processing');
    });
});

it('rejects a webhook with an invalid signature', function () {
    $raw = json_encode(fakeWebhookPayload($this->externalPaymentId, 'succeeded'));

    $this->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], [
        'HTTP_X-Fake-Signature' => 'not-the-real-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertStatus(401)->assertJsonPath('error', 'invalid_webhook_signature');

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Payment::query()->where('external_payment_id', $this->externalPaymentId)->firstOrFail()->status->value)->toBe('processing'));
});

it('rejects a webhook with no signature header at all', function () {
    $raw = json_encode(fakeWebhookPayload($this->externalPaymentId, 'succeeded'));

    $this->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertStatus(401);
});

it('rejects a malformed webhook payload', function () {
    $payload = ['event_id' => 'x', 'external_payment_id' => $this->externalPaymentId];
    $raw = json_encode($payload);
    $signature = hash_hmac('sha256', $raw, (string) config('payments.fake.secret'));

    $this->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], [
        'HTTP_X-Fake-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertStatus(422)->assertJsonPath('error', 'malformed_webhook_payload');
});

it('rejects a webhook for an unknown payment safely', function () {
    signedFakeWebhookRequest(fakeWebhookPayload('fake_does_not_exist', 'succeeded'))
        ->assertStatus(404)
        ->assertJsonPath('error', 'unknown_payment');
});

it('rejects an unknown provider', function () {
    $this->postJson('/api/v1/payments/webhooks/stripe', ['anything' => true])
        ->assertStatus(422)
        ->assertJsonPath('error', 'unknown_payment_provider');
});

it('rejects a webhook whose timestamp is outside the replay tolerance window', function () {
    $stale = now()->subSeconds((int) config('payments.webhook.replay_tolerance_seconds') + 60)->timestamp;

    signedFakeWebhookRequest(fakeWebhookPayload($this->externalPaymentId, 'succeeded', timestamp: $stale))
        ->assertStatus(422)
        ->assertJsonPath('error', 'webhook_replay_rejected');

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Payment::query()->where('external_payment_id', $this->externalPaymentId)->firstOrFail()->status->value)->toBe('processing'));
});

it('is idempotent under a genuinely duplicated webhook delivery: one transition, one transaction, one event row', function () {
    $payload = fakeWebhookPayload($this->externalPaymentId, 'succeeded');

    signedFakeWebhookRequest($payload)->assertOk();
    signedFakeWebhookRequest($payload)->assertOk();
    signedFakeWebhookRequest($payload)->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () use ($payload) {
        $payment = Payment::query()->where('external_payment_id', $this->externalPaymentId)->firstOrFail();
        expect($payment->status->value)->toBe('paid')
            ->and($payment->transactions()->count())->toBe(1);

        expect(PaymentWebhookEvent::withoutGlobalScopes()->where('external_event_id', $payload['event_id'])->count())->toBe(1);
    });
});

it('does not error on a second, genuinely different event confirming an already-terminal payment', function () {
    signedFakeWebhookRequest(fakeWebhookPayload($this->externalPaymentId, 'succeeded'))->assertOk();
    // A different event id, same outcome — out-of-order/redundant
    // provider confirmation, not a literal duplicate delivery.
    signedFakeWebhookRequest(fakeWebhookPayload($this->externalPaymentId, 'succeeded'))->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        $payment = Payment::query()->where('external_payment_id', $this->externalPaymentId)->firstOrFail();
        expect($payment->status->value)->toBe('paid')
            // Both deliveries are recorded (append-only ledger), but only
            // the first actually drove a status transition.
            ->and($payment->transactions()->count())->toBe(2);
    });
});
