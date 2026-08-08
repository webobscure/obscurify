<?php

use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAttempt;
use App\Domain\Payments\Models\PaymentSession;
use App\Domain\Payments\Models\PaymentTransaction;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * Cross-tenant isolation for the whole Payment graph, including nested
 * ownership (an active-tenant switch, not just a differently-scoped row
 * lookup) — mirrors CheckoutTenantIsolationTest from Milestone 3.
 */
beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    domainForStore($this->storeB, 'store-b.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
});

it('completes a real payment for Store A and confirms Store B can never reach any of its rows', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    $payment = $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'isolation-key'],
    )->assertCreated();

    $externalPaymentId = Str::after($payment->json('data.redirect_url'), '/fake-payments/');

    $raw = json_encode([
        'event_id' => (string) Str::ulid(),
        'external_payment_id' => $externalPaymentId,
        'event_type' => 'payment.updated',
        'status' => 'succeeded',
        'amount' => 1000,
        'currency' => 'RUB',
        'timestamp' => now()->timestamp,
    ]);
    $signature = hash_hmac('sha256', $raw, (string) config('payments.fake.secret'));
    $this->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], [
        'HTTP_X-Fake-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertOk();

    [$paymentId, $sessionId, $attemptId, $transactionId] = app(TenantContext::class)->scope($this->storeA, function () use ($externalPaymentId) {
        $payment = Payment::query()->where('external_payment_id', $externalPaymentId)->firstOrFail();

        return [
            $payment->id,
            PaymentSession::query()->where('payment_id', $payment->id)->firstOrFail()->id,
            PaymentAttempt::query()->where('payment_id', $payment->id)->firstOrFail()->id,
            PaymentTransaction::query()->where('payment_id', $payment->id)->firstOrFail()->id,
        ];
    });

    // Nested ownership: Store B's active tenant can reach none of these
    // rows by id.
    app(TenantContext::class)->scope($this->storeB, function () use ($paymentId, $sessionId, $attemptId, $transactionId) {
        expect(Payment::query()->whereKey($paymentId)->first())->toBeNull()
            ->and(PaymentSession::query()->whereKey($sessionId)->first())->toBeNull()
            ->and(PaymentAttempt::query()->whereKey($attemptId)->first())->toBeNull()
            ->and(PaymentTransaction::query()->whereKey($transactionId)->first())->toBeNull();
    });

    // Admin API: Store B's owner, authenticated, with Store B active,
    // cannot view Store A's payment either.
    $this->actingAs($this->userB, 'sanctum')
        ->getJson("/api/v1/payments/{$paymentId}", tenantHeader($this->storeB))
        ->assertNotFound();
});

it('does not let a Store B guest create a payment by guessing a Store A order id', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    $this->postJson(
        storefrontUrl('store-b.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'guessed-order-key'],
    )->assertNotFound();

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Payment::query()->where('order_id', $orderId)->count())->toBe(0));
});
