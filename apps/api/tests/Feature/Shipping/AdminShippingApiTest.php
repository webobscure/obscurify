<?php

use App\Models\User;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    $this->shippingB = shippingSetupForStore($this->storeB);
});

it('creates a shipping zone with regions', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/shipping-zones', [
        'name' => 'Russia',
        'regions' => [
            ['country_code' => 'ru', 'region' => 'Moscow'],
            ['country_code' => 'ru', 'postal_code_pattern' => '190'],
        ],
    ], tenantHeader($this->storeA))->assertCreated();

    expect($response->json('data.name'))->toBe('Russia')
        ->and($response->json('data.status'))->toBe('active')
        ->and($response->json('data.regions'))->toHaveCount(2)
        // Normalized to uppercase (CreateShippingZone::handle).
        ->and($response->json('data.regions.0.country_code'))->toBe('RU');
});

it('lists only the active store zones', function () {
    $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/shipping-zones', ['name' => 'Zone A'], tenantHeader($this->storeA))->assertCreated();

    $response = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/shipping-zones', tenantHeader($this->storeA))->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Zone A']);
});

it('updates a shipping zone, replacing its regions in full', function () {
    $created = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/shipping-zones', [
        'name' => 'Zone A',
        'regions' => [['country_code' => 'US']],
    ], tenantHeader($this->storeA))->assertCreated();

    $updated = $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/shipping-zones/{$created->json('data.id')}", [
        'regions' => [['country_code' => 'CA'], ['country_code' => 'MX']],
    ], tenantHeader($this->storeA))->assertOk();

    expect($updated->json('data.regions'))->toHaveCount(2)
        ->and(collect($updated->json('data.regions'))->pluck('country_code')->sort()->values()->all())->toBe(['CA', 'MX']);
});

it('creates a shipping method attached to zones', function () {
    $zone = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/shipping-zones', ['name' => 'Zone A'], tenantHeader($this->storeA))->assertCreated();

    $response = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/shipping-methods', [
        'name' => 'Courier',
        'code' => 'courier',
        'provider' => 'fake',
        'service_code' => 'standard',
        'price_amount' => 40000,
        'currency' => 'RUB',
        'zone_ids' => [$zone->json('data.id')],
    ], tenantHeader($this->storeA))->assertCreated();

    expect($response->json('data.name'))->toBe('Courier')
        ->and($response->json('data.zone_ids'))->toBe([$zone->json('data.id')]);
});

it('rejects a shipping method referencing another store\'s zone', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/shipping-methods', [
        'name' => 'Courier',
        'code' => 'courier',
        'provider' => 'fake',
        'price_amount' => 40000,
        'currency' => 'RUB',
        'zone_ids' => [$this->shippingB['zone']->id],
    ], tenantHeader($this->storeA));

    $response->assertStatus(422);
    expect($response->json('errors'))->toHaveKey('zone_ids.0');
});

it('rejects an unknown/unregistered provider', function () {
    $this->actingAs($this->userA, 'sanctum')->postJson('/api/v1/shipping-methods', [
        'name' => 'CDEK Courier',
        'code' => 'cdek-courier',
        'provider' => 'cdek',
        'price_amount' => 40000,
        'currency' => 'RUB',
    ], tenantHeader($this->storeA))->assertStatus(422);
});

it('never lets Store A read, update, or attach Store B shipping zones/methods', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/shipping-zones', tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/shipping-zones/{$this->shippingB['zone']->id}", [
        'name' => 'Hijacked',
    ], tenantHeader($this->storeA))->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/shipping-methods/{$this->shippingB['standard']->id}", [
        'name' => 'Hijacked',
    ], tenantHeader($this->storeA))->assertNotFound();
});

it('lists shipments scoped to the active store only', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/shipments', tenantHeader($this->storeA))->assertOk();

    expect($response->json('data'))->toBe([]);
});
