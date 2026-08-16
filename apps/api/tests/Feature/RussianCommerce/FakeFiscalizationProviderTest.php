<?php

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\RussianCommerce\Enums\FiscalReceiptStatus;
use App\Domain\RussianCommerce\Models\FiscalReceipt;
use App\Domain\RussianCommerce\Support\Providers\FakeFiscalizationProvider;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Testing\TestResponse;

/**
 * Signs and posts a fake fiscalization callback exactly like a real OFD
 * provider would — using the provider's own public signing contract
 * (simulateCallbackPayload), never a private method.
 */
function signedFakeFiscalizationCallback(string $externalReceiptId, string $outcome): TestResponse
{
    $simulated = app(FakeFiscalizationProvider::class)->simulateCallbackPayload($externalReceiptId, $outcome);

    return test()->call('POST', '/api/v1/russian-commerce/fiscalization/callbacks/fake', [], [], [], [
        'HTTP_X-Fake-Fiscalization-Signature' => $simulated['signature'],
        'CONTENT_TYPE' => 'application/json',
    ], $simulated['payload']);
}

beforeEach(function () {
    $this->withCredentials();

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    domainForStore($this->store, 'rc-callback.localhost');

    [$this->product, $this->variant] = productWithStock($this->store, 10);

    setUpRussianCommerceStore($this->store);

    ['order_id' => $this->orderId, 'payment_id' => $this->paymentId] = payAndFiscalize('rc-callback.localhost', $this->variant->id);

    $this->receipt = app(TenantContext::class)->scope(
        $this->store,
        fn () => FiscalReceipt::query()->where('order_id', $this->orderId)->firstOrFail(),
    );
});

it('fiscalizes the receipt on a valid signed success callback', function () {
    signedFakeFiscalizationCallback($this->receipt->external_receipt_id, 'success')->assertOk();

    app(TenantContext::class)->scope($this->store, function () {
        $receipt = FiscalReceipt::query()->whereKey($this->receipt->id)->firstOrFail();
        expect($receipt->status)->toBe(FiscalReceiptStatus::Fiscalized)
            ->and($receipt->fiscalized_at)->not->toBeNull();

        expect(OutboxEvent::withoutGlobalScopes()->where('event_type', 'FiscalReceiptFiscalized')->where('aggregate_id', $receipt->id)->exists())->toBeTrue();
    });
});

it('marks the receipt failed on a valid signed failure callback, without touching Order/Payment', function () {
    signedFakeFiscalizationCallback($this->receipt->external_receipt_id, 'failure')->assertOk();

    app(TenantContext::class)->scope($this->store, function () {
        $receipt = FiscalReceipt::query()->whereKey($this->receipt->id)->firstOrFail();
        expect($receipt->status)->toBe(FiscalReceiptStatus::Failed)
            ->and($receipt->error_message)->not->toBeNull();

        // Spec section 15: "Payment success should not directly mean
        // fiscalization success" — a fiscalization failure must never
        // roll back the Order/Payment's own already-paid state.
        expect(Payment::query()->whereKey($this->paymentId)->firstOrFail()->status->value)->toBe('paid')
            ->and(Order::query()->whereKey($this->orderId)->firstOrFail()->financial_status->value)->toBe('paid');

        expect(OutboxEvent::withoutGlobalScopes()->where('event_type', 'FiscalReceiptFailed')->where('aggregate_id', $receipt->id)->exists())->toBeTrue();
    });
});

it('treats a delayed_success callback the same as an immediate success, whenever it eventually arrives', function () {
    signedFakeFiscalizationCallback($this->receipt->external_receipt_id, 'delayed_success')->assertOk();

    app(TenantContext::class)->scope($this->store, function () {
        expect(FiscalReceipt::query()->whereKey($this->receipt->id)->firstOrFail()->status)->toBe(FiscalReceiptStatus::Fiscalized);
    });
});

it('is idempotent under a duplicate/replayed callback delivery', function () {
    signedFakeFiscalizationCallback($this->receipt->external_receipt_id, 'success')->assertOk();
    signedFakeFiscalizationCallback($this->receipt->external_receipt_id, 'success')->assertOk();
    signedFakeFiscalizationCallback($this->receipt->external_receipt_id, 'success')->assertOk();

    app(TenantContext::class)->scope($this->store, function () {
        $receipt = FiscalReceipt::query()->whereKey($this->receipt->id)->firstOrFail();
        expect($receipt->status)->toBe(FiscalReceiptStatus::Fiscalized)
            ->and(OutboxEvent::withoutGlobalScopes()->where('event_type', 'FiscalReceiptFiscalized')->where('aggregate_id', $receipt->id)->count())->toBe(1);
    });
});

it('does not let a failure callback overwrite an already-fiscalized receipt', function () {
    signedFakeFiscalizationCallback($this->receipt->external_receipt_id, 'success')->assertOk();
    signedFakeFiscalizationCallback($this->receipt->external_receipt_id, 'failure')->assertOk();

    app(TenantContext::class)->scope($this->store, function () {
        expect(FiscalReceipt::query()->whereKey($this->receipt->id)->firstOrFail()->status)->toBe(FiscalReceiptStatus::Fiscalized);
    });
});

it('rejects a callback with an invalid signature', function () {
    test()->call('POST', '/api/v1/russian-commerce/fiscalization/callbacks/fake', [], [], [], [
        'HTTP_X-Fake-Fiscalization-Signature' => 'not-the-real-signature',
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['external_receipt_id' => $this->receipt->external_receipt_id, 'status' => 'fiscalized']))
        ->assertStatus(401)
        ->assertJsonPath('error', 'invalid_fiscalization_callback_signature');

    app(TenantContext::class)->scope($this->store, function () {
        expect(FiscalReceipt::query()->whereKey($this->receipt->id)->firstOrFail()->status)->toBe(FiscalReceiptStatus::Processing);
    });
});

it('rejects a callback for an unknown external_receipt_id safely', function () {
    signedFakeFiscalizationCallback('fake_receipt_does_not_exist', 'success')
        ->assertStatus(404)
        ->assertJsonPath('error', 'unknown_fiscal_receipt');
});

it('rejects an unknown fiscalization provider', function () {
    test()->postJson('/api/v1/russian-commerce/fiscalization/callbacks/atol', ['anything' => true])
        ->assertStatus(422)
        ->assertJsonPath('error', 'unknown_fiscalization_provider');
});
