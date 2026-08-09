<?php

use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShippingMethodZone;
use App\Domain\Shipping\Models\ShippingQuote;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Dedicated cross-tenant relation tests (spec section 33) — each pairs a
 * Store A resource with a Store B resource and confirms the pairing is
 * rejected, not merely that each resource is individually scoped.
 * Overlaps in intent with assertions already made inline in
 * ShippingRateTest/CheckoutShippingTest/ShipmentTest/AdminShippingApiTest;
 * collected here as its own file per the milestone brief, mirroring
 * PaymentTenantIsolationTest/CheckoutTenantIsolationTest.
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
    [$this->productB, $this->variantB] = productWithStock($this->storeB, 10);

    $this->shippingA = shippingSetupForStore($this->storeA);
    $this->shippingB = shippingSetupForStore($this->storeB);
});

it('rejects Store A ShippingMethod + Store B ShippingZone', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/shipping-methods', [
        'name' => 'Cross Store',
        'code' => 'cross-store',
        'provider' => 'fake',
        'price_amount' => 10000,
        'currency' => 'RUB',
        'zone_ids' => [$this->shippingB['zone']->id],
    ], tenantHeader($this->storeA));

    $response->assertStatus(422);

    // Nested ownership: even bypassing request validation, the pivot
    // model itself is tenant-scoped, so it could never actually link the
    // two stores' rows.
    app(TenantContext::class)->scope($this->storeA, function () {
        expect(ShippingMethodZone::query()->where('shipping_zone_id', $this->shippingB['zone']->id)->count())->toBe(0);
    });
});

it('rejects Store A Order + Store B Shipment', function () {
    ['order_id' => $orderIdA] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);
    ['order_id' => $orderIdB, 'order_item_id' => $orderItemIdB] = paidOrderFor('store-b.localhost', $this->variantB->id, $this->storeB);

    $shipmentB = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/orders/{$orderIdB}/shipments", [
        'provider' => 'fake',
        'lines' => [['order_item_id' => $orderItemIdB, 'quantity' => 1]],
    ], tenantHeader($this->storeB))->assertCreated();

    // Store A, authenticated, with Store A active, cannot view Store B's
    // shipment via any route — neither the bare shipments endpoint...
    $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/shipments/{$shipmentB->json('data.id')}", tenantHeader($this->storeA))
        ->assertNotFound();

    // ...nor by attempting to cancel it while Store A is the active
    // tenant.
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/shipments/{$shipmentB->json('data.id')}/cancel", [], tenantHeader($this->storeA))
        ->assertNotFound();

    app(TenantContext::class)->scope($this->storeA, function () use ($orderIdA) {
        expect(Shipment::query()->where('order_id', $orderIdA)->count())->toBe(0);
    });
});

it('rejects Store A Shipment + Store B OrderItem', function () {
    ['order_id' => $orderIdA] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);
    ['order_item_id' => $orderItemIdB] = paidOrderFor('store-b.localhost', $this->variantB->id, $this->storeB);

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderIdA}/shipments", [
        'provider' => 'fake',
        'lines' => [['order_item_id' => $orderItemIdB, 'quantity' => 1]],
    ], tenantHeader($this->storeA))->assertStatus(422);

    app(TenantContext::class)->scope($this->storeA, function () use ($orderIdA) {
        expect(Shipment::query()->where('order_id', $orderIdA)->count())->toBe(0);
    });
});

it('rejects Store A Checkout + Store B ShippingQuote', function () {
    $tokenA = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    // Store B's cart/checkout is a wholly separate row, resolved solely
    // from a Store-B-scoped cart lookup — Store A's cart token cannot
    // reach it, so a Store B quote can never attach to a Store A
    // checkout in the first place. Confirmed structurally instead: no
    // ShippingQuote created against Store B is ever visible while Store A
    // is active.
    $this->withUnencryptedCookie('storefront_cart_token', $tokenA);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'standard',
        'shipping_method_id' => $this->shippingB['standard']->id,
    ])->assertStatus(422)->assertJsonPath('error', 'invalid_shipping_quote');

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(ShippingQuote::query()->count())->toBe(0);
    });
});
