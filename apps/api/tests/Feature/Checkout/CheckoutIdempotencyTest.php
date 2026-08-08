<?php

use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * @return string cart token
 */
function openCheckoutReadyToComplete(string $host, string $variantId, int $quantity = 1): string
{
    // Forces a genuinely fresh guest cart even when this helper is called
    // more than once in the same test: without this, the test client's
    // cookie jar would still be carrying the *previous* call's cart
    // token, and GetOrCreateCart would silently reuse (and add to) that
    // already-converted cart instead of starting a new one.
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

    return $token;
}

beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
});

it('replays the identical order on a repeated request with the same Idempotency-Key', function () {
    $token = openCheckoutReadyToComplete('store-a.localhost', $this->variantA->id);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $first = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'replay-key'],
    )->assertCreated();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $second = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'replay-key'],
    )->assertCreated();

    // Content equality, not identity: jsonb does not preserve key
    // insertion order, so the replayed body (read back from the
    // idempotency_keys.response_body column) can have keys in a
    // different order than the freshly-serialized first response even
    // though every value is identical.
    expect($second->json('data'))->toEqual($first->json('data'));

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Order::query()->count())->toBe(1));
});

it('rejects reusing the same Idempotency-Key against a different checkout as a conflicting payload', function () {
    $tokenOne = openCheckoutReadyToComplete('store-a.localhost', $this->variantA->id, 1);

    $this->withUnencryptedCookie('storefront_cart_token', $tokenOne);
    $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'shared-key'],
    )->assertCreated();

    // A second, distinct cart/checkout (no cookie carried over) reusing
    // the same key: the hash folds in the checkout id, so this is a
    // genuinely different operation under the same key, not a replay.
    $tokenTwo = openCheckoutReadyToComplete('store-a.localhost', $this->variantA->id, 1);

    $this->withUnencryptedCookie('storefront_cart_token', $tokenTwo);
    $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'shared-key'],
    )->assertStatus(409)
        ->assertJsonPath('error', 'idempotency_key_conflict');

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Order::query()->count())->toBe(1));
});

it('lets a fresh Idempotency-Key complete a second, independent checkout normally', function () {
    $tokenOne = openCheckoutReadyToComplete('store-a.localhost', $this->variantA->id, 1);
    $this->withUnencryptedCookie('storefront_cart_token', $tokenOne);
    $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'first-key'],
    )->assertCreated();

    $tokenTwo = openCheckoutReadyToComplete('store-a.localhost', $this->variantA->id, 1);
    $this->withUnencryptedCookie('storefront_cart_token', $tokenTwo);
    $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'second-key'],
    )->assertCreated();

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Order::query()->count())->toBe(2));
});
