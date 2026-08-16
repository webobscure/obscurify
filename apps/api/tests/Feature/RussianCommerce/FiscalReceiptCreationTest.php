<?php

use App\Domain\RussianCommerce\Application\CreateOrUpdateLegalProfile;
use App\Domain\RussianCommerce\Application\EnsureDefaultRussianCommerceSetup;
use App\Domain\RussianCommerce\Application\FiscalizationSubscriber;
use App\Domain\RussianCommerce\Application\UpdateFiscalizationSettings;
use App\Domain\RussianCommerce\Enums\FiscalReceiptStatus;
use App\Domain\RussianCommerce\Models\FiscalizationSettings;
use App\Domain\RussianCommerce\Models\FiscalReceipt;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * Seeds a store with a valid legal profile and receipts_required=true —
 * every order placed after this call is eligible for fiscalization
 * (see BuildOrderFiscalSnapshot).
 */
function setUpRussianCommerceStore(Store $store): void
{
    app(TenantContext::class)->scope($store, function () use ($store) {
        app(EnsureDefaultRussianCommerceSetup::class)->handle($store);

        app(CreateOrUpdateLegalProfile::class)->handle($store, [
            'legal_entity_type' => 'legal_entity',
            'legal_name' => 'OOO Test Store',
            'inn' => '7707083893',
            'kpp' => '770701001',
        ]);

        $settings = FiscalizationSettings::query()->where('store_id', $store->id)->firstOrFail();
        app(UpdateFiscalizationSettings::class)->handle($settings, ['receipts_required' => true]);
    });
}

/**
 * Completes a checkout, creates+captures a fake payment via the signed
 * webhook flow (mirrors PaymentWebhookTest), then drives the resulting
 * OrderPaymentConfirmed event through FiscalizationSubscriber directly
 * — QUEUE_CONNECTION=sync in testing, so the RequestFiscalizationJob it
 * dispatches runs immediately.
 *
 * @return array{order_id: string, payment_id: string}
 */
function payAndFiscalize(string $host, string $variantId): array
{
    ['order_id' => $orderId] = completedOrderFor($host, $variantId);

    $payment = test()->postJson(
        storefrontUrl($host, "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'fiscal-test-'.Str::random(8)],
    )->assertCreated();

    $externalPaymentId = Str::after($payment->json('data.redirect_url'), '/fake-payments/');
    $paymentId = $payment->json('data.id');

    $raw = json_encode([
        'event_id' => (string) Str::ulid(),
        'external_payment_id' => $externalPaymentId,
        'event_type' => 'payment.updated',
        'status' => 'succeeded',
        'amount' => $payment->json('data.amount'),
        'currency' => $payment->json('data.currency'),
        'timestamp' => now()->timestamp,
    ]);
    $signature = hash_hmac('sha256', $raw, (string) config('payments.fake.secret'));

    test()->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], [
        'HTTP_X-Fake-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertOk();

    $event = OutboxEvent::withoutGlobalScopes()
        ->where('event_type', 'OrderPaymentConfirmed')
        ->where('aggregate_id', $orderId)
        ->latest('occurred_at')
        ->firstOrFail();

    $store = Store::query()->findOrFail($event->store_id);
    app(TenantContext::class)->scope($store, fn () => app(FiscalizationSubscriber::class)->handle($event, $store));

    return ['order_id' => $orderId, 'payment_id' => $paymentId];
}

beforeEach(function () {
    $this->withCredentials();

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    domainForStore($this->store, 'rc-store.localhost');

    [$this->product, $this->variant] = productWithStock($this->store, 10);
});

it('creates no fiscal receipt when the store has no legal profile configured', function () {
    ['order_id' => $orderId] = payAndFiscalize('rc-store.localhost', $this->variant->id);

    app(TenantContext::class)->scope($this->store, function () use ($orderId) {
        expect(FiscalReceipt::query()->where('order_id', $orderId)->exists())->toBeFalse();
    });
});

it('creates a fiscal receipt and submits it to the fake provider when receipts are required', function () {
    setUpRussianCommerceStore($this->store);

    ['order_id' => $orderId, 'payment_id' => $paymentId] = payAndFiscalize('rc-store.localhost', $this->variant->id);

    app(TenantContext::class)->scope($this->store, function () use ($orderId, $paymentId) {
        $receipt = FiscalReceipt::query()->where('order_id', $orderId)->with('items')->firstOrFail();

        expect($receipt->status)->toBe(FiscalReceiptStatus::Processing)
            ->and($receipt->provider)->toBe('fake')
            ->and($receipt->payment_id)->toBe($paymentId)
            ->and($receipt->external_receipt_id)->toStartWith('fake_receipt_')
            ->and($receipt->seller_inn)->toBe('7707083893')
            ->and($receipt->seller_kpp)->toBe('770701001')
            ->and($receipt->items)->toHaveCount(1)
            ->and($receipt->items->first()->amount)->toBe(1000);

        expect(OutboxEvent::withoutGlobalScopes()->where('event_type', 'FiscalReceiptCreated')->where('aggregate_id', $receipt->id)->exists())->toBeTrue();
    });
});
