<?php

use App\Domain\Orders\Models\OrderShippingLine;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingMethodZone;
use App\Domain\Shipping\Models\ShippingQuote;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Models\ShippingZoneRegion;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * @return array{zone: ShippingZone, pickup: ShippingMethod}
 */
function ruPickupSetupForStore(Store $store): array
{
    return app(TenantContext::class)->scope($store, function () {
        $zone = ShippingZone::factory()->create(['name' => 'Russia']);
        ShippingZoneRegion::factory()->create([
            'shipping_zone_id' => $zone->id,
            'country_code' => 'RU',
            'region' => null,
        ]);

        $pickup = ShippingMethod::factory()->pickup()->create();
        ShippingMethodZone::query()->create(['shipping_method_id' => $pickup->id, 'shipping_zone_id' => $zone->id]);

        return compact('zone', 'pickup');
    });
}

beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);

    $this->ruPickup = ruPickupSetupForStore($this->storeA);
});

it('returns real pickup points for a matching (RU) destination', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'RU', 'city' => 'Moscow',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $rates = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping-rates'))->assertOk();

    $pickup = collect($rates->json('data'))->firstWhere('service_code', 'pickup');
    expect($pickup['pickup_points'])->not->toBeEmpty()
        ->and($pickup['pickup_points'][0])->toHaveKeys(['id', 'name', 'address', 'city', 'country_code', 'postal_code', 'opening_hours', 'latitude', 'longitude']);
});

it('selects a pickup rate with a valid pickup point and persists the snapshot through to the order', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'RU', 'city' => 'Moscow',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $rates = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping-rates'))->assertOk();
    $pickup = collect($rates->json('data'))->firstWhere('service_code', 'pickup');
    $pointId = $pickup['pickup_points'][0]['id'];

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'pickup',
        'shipping_method_id' => $this->ruPickup['pickup']->id,
        'pickup_point_id' => $pointId,
    ])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () use ($pointId) {
        $quote = ShippingQuote::query()->firstOrFail();
        expect($quote->metadata['pickup_point']['id'])->toBe($pointId);
    });

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'pickup-complete-key'],
    )->assertCreated();

    expect($complete->json('data.shipping_line.service_code'))->toBe('pickup');

    app(TenantContext::class)->scope($this->storeA, function () use ($pointId) {
        $line = OrderShippingLine::query()->firstOrFail();
        expect($line->metadata['pickup_point']['id'])->toBe($pointId);
    });
});

it('requires a pickup point when the pickup service is selected', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'RU', 'city' => 'Moscow',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'pickup',
        'shipping_method_id' => $this->ruPickup['pickup']->id,
    ])->assertStatus(422)->assertJsonPath('error', 'invalid_pickup_point');
});

it('rejects an invented pickup point id', function () {
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'RU', 'city' => 'Moscow',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'pickup',
        'shipping_method_id' => $this->ruPickup['pickup']->id,
        'pickup_point_id' => 'not-a-real-point',
    ])->assertStatus(422)->assertJsonPath('error', 'invalid_pickup_point');
});

it('rejects a pickup point id that does not match the destination context', function () {
    // US destination — the fake network is RU-only, so no point can ever
    // legitimately match here, even one whose id looks plausible.
    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'city' => 'New York',
    ]);

    app(TenantContext::class)->scope($this->storeA, function () {
        $zone = ShippingZone::factory()->create(['name' => 'US Zone']);
        ShippingZoneRegion::factory()->create(['shipping_zone_id' => $zone->id, 'country_code' => 'US']);
        ShippingMethodZone::query()->create(['shipping_method_id' => $this->ruPickup['pickup']->id, 'shipping_zone_id' => $zone->id]);
    });

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'pickup',
        'shipping_method_id' => $this->ruPickup['pickup']->id,
        'pickup_point_id' => 'fake-pickup-moscow-1',
    ])->assertStatus(422)->assertJsonPath('error', 'invalid_pickup_point');
});

it('rejects a pickup_point_id supplied for a non-pickup service', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $standard = ShippingMethod::factory()->create();
        ShippingMethodZone::query()->create(['shipping_method_id' => $standard->id, 'shipping_zone_id' => $this->ruPickup['zone']->id]);
        $this->standard = $standard;
    });

    $token = checkoutTokenWithAddress('store-a.localhost', $this->variantA->id, [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'RU', 'city' => 'Moscow',
    ]);

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/shipping'), [
        'provider' => 'fake',
        'service_code' => 'standard',
        'shipping_method_id' => $this->standard->id,
        'pickup_point_id' => 'fake-pickup-moscow-1',
    ])->assertStatus(422)->assertJsonPath('error', 'invalid_pickup_point');
});
