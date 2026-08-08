<?php

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Cross-tenant isolation for the whole checkout/order graph. Ownership on
 * every storefront checkout endpoint is decided solely by the visitor's
 * own secure cart cookie (never a client-supplied id), and every model in
 * this graph carries BelongsToTenant — these tests exercise both layers
 * together, including the "nested" case where a Store A cart token is
 * replayed against Store B (a currently-active-tenant switch, not just a
 * differently-scoped row lookup).
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

it('completes a full checkout for Store A and confirms Store B can never reach any of its rows', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();
    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'), [
        'email' => 'isolation-buyer@example.com',
        'shipping_address' => [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'city' => 'San Francisco',
        ],
    ])->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'isolation-key'],
    )->assertCreated();

    $orderId = $complete->json('data.id');

    [$checkoutId, $customerId, $reservationId] = app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        $order = Order::query()->whereKey($orderId)->firstOrFail();
        $reservation = InventoryReservation::query()->where('order_id', $orderId)->firstOrFail();

        return [$order->checkout_id, $order->customer_id, $reservation->id];
    });

    // Nested ownership: Store B's own admin API and storefront domain can
    // reach none of Store A's rows produced by this checkout, by id,
    // while Store B is the active tenant.
    app(TenantContext::class)->scope($this->storeB, function () use ($orderId, $checkoutId, $customerId, $reservationId) {
        expect(Order::query()->whereKey($orderId)->first())->toBeNull()
            ->and(Checkout::query()->whereKey($checkoutId)->first())->toBeNull()
            ->and(Customer::query()->whereKey($customerId)->first())->toBeNull()
            ->and(InventoryReservation::query()->whereKey($reservationId)->first())->toBeNull();
    });

    // The storefront order-confirmation endpoint is host-resolved to
    // Store B here — Order::class's own tenant scope makes this 404
    // regardless of the id being perfectly valid under Store A.
    $this->getJson(storefrontUrl('store-b.localhost', "/api/v1/storefront/orders/{$orderId}"))
        ->assertNotFound();

    // And the admin API: Store B's owner, authenticated, with Store B
    // selected as the active tenant, cannot view Store A's order either.
    $this->actingAs($this->userB, 'sanctum')
        ->getJson("/api/v1/orders/{$orderId}", tenantHeader($this->storeB))
        ->assertNotFound();
});

it('does not let a Store B guest complete a checkout by guessing a Store A checkout id', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();
    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $open = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    $checkoutIdA = $open->json('data.id');

    // Store B has no cart matching this token at all (Cart is
    // tenant-scoped), so a fresh, empty Store B cart is silently
    // provisioned instead of ever touching Store A's checkout — the
    // client-side checkout id is never trusted as an authorization token.
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(
        storefrontUrl('store-b.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'guessed-checkout-key'],
    )->assertNotFound();

    app(TenantContext::class)->scope($this->storeA, function () use ($checkoutIdA) {
        expect(Checkout::query()->whereKey($checkoutIdA)->firstOrFail()->status->value)->toBe('open');
    });
});
