<?php

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * Completes a real checkout via the storefront API and returns the
 * resulting order id — payment tests build on top of a genuine Order
 * rather than a factory-created one, since CreatePayment's guards read
 * Order.total_amount/currency/financial_status the same way the real
 * flow produces them.
 *
 * @return array{token: string, order_id: string}
 */
function completedOrderFor(string $host, string $variantId, int $quantity = 1): array
{
    test()->withUnencryptedCookie('storefront_cart_token', Str::random(48));

    $add = test()->postJson(storefrontUrl($host, '/api/v1/storefront/cart/items'), [
        'variant_id' => $variantId,
        'quantity' => $quantity,
    ])->assertOk();

    $token = $add->headers->getCookies()[0]->getValue();
    test()->withUnencryptedCookie('storefront_cart_token', $token);
    test()->postJson(storefrontUrl($host, '/api/v1/storefront/checkout'))->assertOk();

    test()->withUnencryptedCookie('storefront_cart_token', $token);
    test()->patchJson(storefrontUrl($host, '/api/v1/storefront/checkout'), [
        'email' => 'buyer@example.com',
        'shipping_address' => [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'city' => 'San Francisco',
        ],
    ])->assertOk();

    test()->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = test()->postJson(
        storefrontUrl($host, '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'order-'.Str::random(16)],
    )->assertCreated();

    return ['token' => $token, 'order_id' => $complete->json('data.id')];
}

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

it('creates a pending payment for a pending order, deriving amount and currency from the order', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id, 2);

    $response = $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'create-key-1'],
    )->assertCreated();

    expect($response->json('data.status'))->toBe('processing')
        ->and($response->json('data.provider'))->toBe('fake')
        ->and($response->json('data.amount'))->toBe(2000)
        ->and($response->json('data.currency'))->toBe('RUB')
        ->and($response->json('data.redirect_url'))->toStartWith('/fake-payments/');

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        $payment = Payment::query()->where('order_id', $orderId)->firstOrFail();
        expect($payment->amount)->toBe(2000)
            ->and($payment->currency)->toBe('RUB')
            ->and($payment->external_payment_id)->not->toBeNull()
            ->and($payment->attempts()->count())->toBe(1)
            ->and($payment->sessions()->count())->toBe(1);
    });
});

it('rejects a client-supplied amount/currency, always deriving from the order', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id, 1);

    $response = $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake', 'amount' => 999999, 'currency' => 'EUR'],
        ['Idempotency-Key' => 'create-key-2'],
    )->assertCreated();

    // The extra fields are silently ignored — CreatePayment's signature
    // has no amount/currency parameter at all, so there is nothing for a
    // client-supplied value to override.
    expect($response->json('data.amount'))->toBe(1000)
        ->and($response->json('data.currency'))->toBe('RUB');
});

it('requires an Idempotency-Key header', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
    )->assertStatus(422);
});

it('rejects an unknown provider', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'stripe'],
        ['Idempotency-Key' => 'unknown-provider-key'],
    )->assertStatus(422)
        ->assertJsonPath('error', 'unknown_payment_provider');
});

it('rejects creating a second payment while one already exists for the order', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'first-attempt'],
    )->assertCreated();

    $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'second-attempt'],
    )->assertStatus(422);

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Payment::query()->where('order_id', $orderId)->count())->toBe(1));
});

it('rejects payment creation for an already-paid order', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        Order::query()->whereKey($orderId)->update(['financial_status' => 'paid']);
    });

    $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'already-paid-key'],
    )->assertStatus(422);
});

it('rejects payment creation for a cancelled order', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        Order::query()->whereKey($orderId)->update(['order_status' => 'cancelled']);
    });

    $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'cancelled-order-key'],
    )->assertStatus(422);
});

it('replays the identical payment on a repeated request with the same Idempotency-Key', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    $first = $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'replay-key'],
    )->assertCreated();

    $second = $this->postJson(
        storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'replay-key'],
    )->assertCreated();

    expect($second->json('data'))->toEqual($first->json('data'));

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Payment::query()->where('order_id', $orderId)->count())->toBe(1));
});

it('does not let Store B create a payment for a Store A order', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    $this->postJson(
        storefrontUrl('store-b.localhost', "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'cross-store-key'],
    )->assertNotFound();
});
