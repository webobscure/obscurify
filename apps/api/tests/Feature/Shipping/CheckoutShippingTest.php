<?php

use App\Domain\Shipping\Models\ShippingQuote;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    domainForStore($this->storeB, 'store-b.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);

    $this->shipping = shippingSetupForStore($this->storeA);
});

it('selects a shipping rate, persists a quote, and updates the checkout total', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $selected = $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'standard',
        'shipping_method_id' => $this->shipping['standard']->id,
    ])->assertOk();

    expect($selected->json('data.shipping_amount'))->toBe(50000)
        ->and($selected->json('data.total_amount'))->toBe(51000)
        ->and($selected->json('data.selected_shipping_rate.name'))->toBe('Standard Shipping')
        ->and($selected->json('data.selected_shipping_rate.price_amount'))->toBe(50000);

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(ShippingQuote::query()->count())->toBe(1);
    });
});

it('cannot be overridden by a frontend-submitted price — the price always comes from a fresh server-side calculation', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $selected = $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'standard',
        'shipping_method_id' => $this->shipping['standard']->id,
        // Not part of SelectCheckoutShippingRequest's validated fields at
        // all — even if a client includes a price, it's silently ignored,
        // not merely overwritten.
        'price_amount' => 1,
    ])->assertOk();

    expect($selected->json('data.shipping_amount'))->toBe(50000);
});

it('includes the selected shipping cost in the completed order total and snapshot', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'express',
        'shipping_method_id' => $this->shipping['express']->id,
    ])->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'shipping-complete-key'],
    )->assertCreated();

    expect($complete->json('data.shipping_amount'))->toBe(150000)
        ->and($complete->json('data.total_amount'))->toBe(151000)
        ->and($complete->json('data.shipping_line.service_code'))->toBe('express')
        ->and($complete->json('data.shipping_line.price_amount'))->toBe(150000)
        ->and($complete->json('data.shipping_line.estimated_days_max'))->toBe(2);
});

it('rejects completion with an expired shipping quote', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'standard',
        'shipping_method_id' => $this->shipping['standard']->id,
    ])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        ShippingQuote::query()->update(['expires_at' => now()->subMinute()]);
    });

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'expired-quote-key'],
    )->assertStatus(409)->assertJsonPath('error', 'shipping_quote_expired');
});

it('rejects completion when the selected method has since gone inactive', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'standard',
        'shipping_method_id' => $this->shipping['standard']->id,
    ])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        $this->shipping['standard']->update(['status' => 'inactive']);
    });

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'inactive-method-key'],
    )->assertStatus(422)->assertJsonPath('error', 'invalid_shipping_quote');
});

it('completes without any shipping selection, exactly like before this milestone', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'no-shipping-key'],
    )->assertCreated();

    expect($complete->json('data.shipping_amount'))->toBe(0)
        ->and($complete->json('data.shipping_line'))->toBeNull();
});

it('never lets a Store A quote be selected against or read from a Store B checkout', function () {
    $tokenA = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);
    $this->withUnencryptedCookie('storefront_cart_token', $tokenA);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'standard',
        'shipping_method_id' => $this->shipping['standard']->id,
    ])->assertOk();

    $quoteId = app(TenantContext::class)->scope($this->storeA, fn () => ShippingQuote::query()->firstOrFail()->id);

    // The cart-token-scoped cross-store isolation that already protects
    // checkouts (see CheckoutTenantIsolationTest) equally protects this
    // quote: replaying Store A's cookie against Store B just yields a
    // fresh, quote-less Store B checkout — the Store A quote is never
    // reachable, exposed, or attachable from there.
    $this->withUnencryptedCookie('storefront_cart_token', $tokenA);
    $this->patchJson(storefrontUrl('store-b.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'standard',
    ])->assertNotFound();

    app(TenantContext::class)->scope($this->storeB, function () use ($quoteId) {
        expect(ShippingQuote::withoutGlobalScopes()->whereKey($quoteId)->first()->store_id)->toBe($this->storeA->id);
    });
});
