<?php

use App\Domain\Locations\Models\Location;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    $this->locationB = app(TenantContext::class)->scope($this->storeB, fn () => Location::factory()->create());
});

it('creates and updates a location within the active store', function () {
    $id = $this->actingAs($this->userA, 'sanctum')
        ->postJson('/api/v1/locations', ['name' => 'Main Warehouse', 'city' => 'Moscow'], tenantHeader($this->storeA))
        ->assertCreated()
        ->assertJsonPath('data.store_id', $this->storeA->id)
        ->json('data.id');

    $this->actingAs($this->userA, 'sanctum')
        ->patchJson("/api/v1/locations/{$id}", ['city' => 'Saint Petersburg'], tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonPath('data.city', 'Saint Petersburg');
});

it('never lists or updates a Store B location while Store A is active', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/locations', tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->userA, 'sanctum')
        ->patchJson("/api/v1/locations/{$this->locationB->id}", ['name' => 'Hacked'], tenantHeader($this->storeA))
        ->assertNotFound();

    expect($this->locationB->fresh()->name)->not->toBe('Hacked');
});
