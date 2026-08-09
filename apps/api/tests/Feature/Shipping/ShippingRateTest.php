<?php

use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingMethodZone;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Models\ShippingZoneRegion;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Creates a zone matching US/CA plus three fake methods (standard/express/
 * pickup) attached to it — the "example fake services" shape from the
 * milestone brief.
 *
 * @return array{zone: ShippingZone, standard: ShippingMethod, express: ShippingMethod, pickup: ShippingMethod}
 */
function shippingSetupForStore(Store $store): array
{
    return app(TenantContext::class)->scope($store, function () {
        $zone = ShippingZone::factory()->create(['name' => 'US West']);
        ShippingZoneRegion::factory()->create([
            'shipping_zone_id' => $zone->id,
            'country_code' => 'US',
            'region' => 'CA',
        ]);

        $standard = ShippingMethod::factory()->create();
        $express = ShippingMethod::factory()->express()->create();
        $pickup = ShippingMethod::factory()->pickup()->create();

        foreach ([$standard, $express, $pickup] as $method) {
            ShippingMethodZone::query()->create([
                'shipping_method_id' => $method->id,
                'shipping_zone_id' => $zone->id,
            ]);
        }

        return compact('zone', 'standard', 'express', 'pickup');
    });
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

    $this->shipping = shippingSetupForStore($this->storeA);
});

function checkoutTokenWithAddress(string $host, string $variantId, array $address): string
{
    $add = test()->postJson(storefrontUrl($host, '/api/v1/storefront/cart/items'), [
        'variant_id' => $variantId,
        'quantity' => 1,
    ])->assertOk();

    $token = $add->headers->getCookies()[0]->getValue();
    test()->withUnencryptedCookie('storefront_cart_token', $token);
    test()->postJson(storefrontUrl($host, '/api/v1/storefront/checkout'))->assertOk();

    test()->withUnencryptedCookie('storefront_cart_token', $token);
    test()->patchJson(storefrontUrl($host, '/api/v1/storefront/checkout'), [
        'email' => 'buyer@example.com',
        'shipping_address' => $address,
    ])->assertOk();

    return $token;
}

it('returns standard/express/pickup rates for a matching destination', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $rates = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping-rates'))->assertOk();

    $serviceCodes = collect($rates->json('data'))->pluck('service_code')->sort()->values()->all();
    expect($serviceCodes)->toBe(['express', 'pickup', 'standard']);

    // Reference Provider Hardening: price is no longer the flat
    // ShippingMethod.price_amount — 'standard'/'express'/'pickup' are
    // configured service codes (commerce.shipping.fake.services), priced
    // from base_price_amount + weight cost (0 here, the variant has no
    // weight — see ShipmentWeightCalculator's documented policy), with a
    // US destination triggering the international surcharge (US !=
    // commerce.shipping.fake.domestic_country_code, 'RU'):
    // standard 30000 * 1.5 = 45000, express 80000 * 1.5 = 120000.
    // The full weight/international-surcharge breakdown lives in
    // ShippingRate::$metadata, deliberately never exposed to the
    // storefront (StorefrontShippingRateResource) — see
    // ShippingRateAlgorithmTest for those assertions against the
    // provider directly.
    $standard = collect($rates->json('data'))->firstWhere('service_code', 'standard');
    expect($standard['price_amount'])->toBe(45000)
        ->and($standard['currency'])->toBe('RUB')
        ->and($standard['estimated_days_min'])->toBe(3)
        ->and($standard['estimated_days_max'])->toBe(5)
        ->and($standard)->not->toHaveKey('metadata');

    $express = collect($rates->json('data'))->firstWhere('service_code', 'express');
    expect($express['price_amount'])->toBe(120000)
        ->and($express['estimated_days_max'])->toBe(2);

    $pickup = collect($rates->json('data'))->firstWhere('service_code', 'pickup');
    // The destination is US in this test's fixture — the fake provider's
    // static pickup network is RU-only, so no points match here (spec
    // section 6: "matches the destination context where applicable") —
    // the pickup *rate* still appears (ShippingMethod/zone governs that),
    // it just has nothing to offer for this particular address.
    expect($pickup['pickup_points'])->toBe([]);
});

it('hides an inactive method from rate results', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $this->shipping['express']->update(['status' => 'inactive']);
    });

    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $rates = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping-rates'))->assertOk();

    expect(collect($rates->json('data'))->pluck('service_code')->sort()->values()->all())->toBe(['pickup', 'standard']);
});

it('returns no shipping methods available for a destination outside every zone', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'FR', 'city' => 'Paris',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping-rates'))
        ->assertStatus(422)
        ->assertJsonPath('error', 'no_shipping_methods_available');
});

it('returns no shipping methods available for the right country but wrong region', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'NY', 'city' => 'New York',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping-rates'))
        ->assertStatus(422)
        ->assertJsonPath('error', 'no_shipping_methods_available');
});

it('requires a shipping address before rates can be requested', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();
    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping-rates'))
        ->assertStatus(422);
});

it('never returns Store B rates/zones/methods for a Store A checkout', function () {
    // Store B has its own, differently-priced, matching zone/method setup.
    app(TenantContext::class)->scope($this->storeB, function () {
        $zone = ShippingZone::factory()->create();
        ShippingZoneRegion::factory()->create(['shipping_zone_id' => $zone->id, 'country_code' => 'US', 'region' => 'CA']);
        $method = ShippingMethod::factory()->create(['price_amount' => 999999]);
        ShippingMethodZone::query()->create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id]);
    });

    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'region' => 'CA', 'city' => 'San Francisco',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $rates = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping-rates'))->assertOk();

    expect(collect($rates->json('data'))->pluck('price_amount'))->not->toContain(999999);
});
