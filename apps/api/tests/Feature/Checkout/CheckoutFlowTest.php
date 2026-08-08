<?php

use App\Domain\Carts\Models\Cart;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Checkouts\Enums\CheckoutStatus;
use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * @return array{token: string, checkout_id: string}
 */
function openCheckoutWithShipping(string $host, string $variantId, int $quantity = 1): array
{
    $add = test()->postJson(storefrontUrl($host, '/api/v1/storefront/cart/items'), [
        'variant_id' => $variantId,
        'quantity' => $quantity,
    ])->assertOk();

    $token = $add->headers->getCookies()[0]->getValue();
    test()->withUnencryptedCookie('storefront_cart_token', $token);

    $open = test()->postJson(storefrontUrl($host, '/api/v1/storefront/checkout'))->assertOk();
    $checkoutId = $open->json('data.id');

    test()->withUnencryptedCookie('storefront_cart_token', $token);
    test()->patchJson(storefrontUrl($host, '/api/v1/storefront/checkout'), [
        'email' => 'buyer@example.com',
        'phone' => '+15551234567',
        'shipping_address' => [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'country_code' => 'US',
            'region' => 'CA',
            'city' => 'San Francisco',
            'postal_code' => '94107',
            'address_line1' => '123 Analytical Engine Way',
        ],
    ])->assertOk();

    return ['token' => $token, 'checkout_id' => $checkoutId];
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

it('opens a checkout from a cart with correct totals', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 3,
    ])->assertOk();

    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);

    $open = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    expect($open->json('data.items_subtotal_amount'))->toBe(3000)
        ->and($open->json('data.total_amount'))->toBe(3000)
        ->and($open->json('data.status'))->toBe('open')
        ->and($open->json('data.shipping_address'))->toBeNull();
});

it('rejects opening a checkout for an empty cart', function () {
    $cart = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart'))->assertOk();
    $token = $cart->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);

    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertStatus(422);
});

it('reuses the same open checkout across repeat opens for one cart', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();
    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);

    $first = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $second = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
});

it('updates contact info and shipping address, defaulting billing to shipping', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();
    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $update = $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'), [
        'email' => 'buyer@example.com',
        'phone' => '+15551234567',
        'shipping_address' => [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'country_code' => 'US',
            'region' => 'CA',
            'city' => 'San Francisco',
            'postal_code' => '94107',
            'address_line1' => '123 Analytical Engine Way',
        ],
    ])->assertOk();

    expect($update->json('data.email'))->toBe('buyer@example.com')
        ->and($update->json('data.phone'))->toBe('+15551234567')
        ->and($update->json('data.shipping_address.city'))->toBe('San Francisco')
        ->and($update->json('data.billing_address.city'))->toBe('San Francisco')
        ->and($update->json('data.billing_address.first_name'))->toBe('Ada');
});

it('keeps a distinct billing address when billing_same_as_shipping is false', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();
    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $update = $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'), [
        'shipping_address' => [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'city' => 'San Francisco',
        ],
        'billing_same_as_shipping' => false,
        'billing_address' => [
            'first_name' => 'Charles', 'last_name' => 'Babbage', 'country_code' => 'GB', 'city' => 'London',
        ],
    ])->assertOk();

    expect($update->json('data.shipping_address.city'))->toBe('San Francisco')
        ->and($update->json('data.billing_address.city'))->toBe('London');
});

it('completes checkout: creates a pending order, reserves inventory, converts the cart', function () {
    ['token' => $token] = openCheckoutWithShipping('store-a.localhost', $this->variantA->id, 2);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'flow-key-1'],
    )->assertCreated();

    expect($complete->json('data.number'))->toBeGreaterThanOrEqual(1001)
        ->and($complete->json('data.order_status'))->toBe('open')
        ->and($complete->json('data.financial_status'))->toBe('pending')
        ->and($complete->json('data.fulfillment_status'))->toBe('unfulfilled')
        ->and($complete->json('data.total_amount'))->toBe(2000)
        ->and($complete->json('data.items.0.quantity'))->toBe(2);

    app(TenantContext::class)->scope($this->storeA, function () {
        $cart = Cart::query()->first();
        expect($cart->status)->toBe('converted');

        $checkout = Checkout::query()->first();
        expect($checkout->status)->toBe(CheckoutStatus::Completed);

        $level = InventoryLevel::query()->first();
        expect($level->on_hand)->toBe(10)
            ->and($level->reserved)->toBe(2);
    });
});

it('requires an Idempotency-Key header to complete checkout', function () {
    ['token' => $token] = openCheckoutWithShipping('store-a.localhost', $this->variantA->id);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'))
        ->assertStatus(422);
});

it('rejects completion without a shipping address', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();
    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'no-address-key'],
    )->assertStatus(422);
});

it('rejects completing an expired checkout and marks it expired', function () {
    ['token' => $token, 'checkout_id' => $checkoutId] = openCheckoutWithShipping('store-a.localhost', $this->variantA->id);

    app(TenantContext::class)->scope($this->storeA, function () use ($checkoutId) {
        Checkout::query()->whereKey($checkoutId)->update(['expires_at' => now()->subMinute()]);
    });

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'expired-key'],
    )->assertStatus(422);

    app(TenantContext::class)->scope($this->storeA, function () use ($checkoutId) {
        expect(Checkout::query()->whereKey($checkoutId)->firstOrFail()->status)->toBe(CheckoutStatus::Expired);
    });
});

it('rejects completing when stock dropped below the cart quantity after checkout was opened', function () {
    ['token' => $token] = openCheckoutWithShipping('store-a.localhost', $this->variantA->id, 5);

    app(TenantContext::class)->scope($this->storeA, function () {
        InventoryLevel::query()->update(['on_hand' => 1]);
    });

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'insufficient-stock-key'],
    )->assertStatus(422);

    app(TenantContext::class)->scope($this->storeA, fn () => expect(Order::query()->count())->toBe(0));
});

it('recalculates the order total from the current variant price, ignoring the stale checkout snapshot', function () {
    ['token' => $token] = openCheckoutWithShipping('store-a.localhost', $this->variantA->id, 1);

    app(TenantContext::class)->scope($this->storeA, function () {
        ProductVariant::query()->whereKey($this->variantA->id)->update(['price_amount' => 5000]);
    });

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'repriced-key'],
    )->assertCreated();

    expect($complete->json('data.total_amount'))->toBe(5000)
        ->and($complete->json('data.items.0.unit_price_amount'))->toBe(5000);
});

it('does not expose or let Store B touch a Store A checkout via its cart token', function () {
    ['token' => $token] = openCheckoutWithShipping('store-a.localhost', $this->variantA->id);

    // Same cookie value replayed against Store B: Cart lookup is
    // tenant-scoped so this token simply matches nothing there, and the
    // storefront transparently issues a fresh empty Store B cart instead.
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-b.localhost', '/api/v1/storefront/checkout'), [
        'email' => 'attacker@example.com',
    ])->assertNotFound();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(
        storefrontUrl('store-b.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'cross-store-key'],
    )->assertNotFound();
});
