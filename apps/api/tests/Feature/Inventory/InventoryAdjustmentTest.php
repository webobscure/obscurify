<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Locations\Models\Location;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->productA = app(TenantContext::class)->scope($this->storeA, fn () => Product::factory()->create());
    $this->locationA = app(TenantContext::class)->scope($this->storeA, fn () => Location::factory()->create());

    $variantResponse = $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/products/{$this->productA->id}/variants", ['price_amount' => 1000], tenantHeader($this->storeA))
        ->assertCreated();

    $this->itemA = app(TenantContext::class)->scope(
        $this->storeA,
        fn () => InventoryItem::query()->where('product_variant_id', $variantResponse->json('data.id'))->firstOrFail(),
    );

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    $this->productB = app(TenantContext::class)->scope($this->storeB, fn () => Product::factory()->create());
    $this->locationB = app(TenantContext::class)->scope($this->storeB, fn () => Location::factory()->create());
});

it('adjusts on-hand stock and records a movement', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/inventory/{$this->itemA->id}/adjust", [
            'location_id' => $this->locationA->id,
            'quantity_delta' => 25,
            'reason' => InventoryMovementReason::InitialStock->value,
        ], tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonPath('data.on_hand', 25)
        ->assertJsonPath('data.available', 25);

    app(TenantContext::class)->scope($this->storeA, function () {
        $level = InventoryLevel::query()->where('inventory_item_id', $this->itemA->id)->where('location_id', $this->locationA->id)->firstOrFail();
        expect($level->on_hand)->toBe(25);

        $movement = InventoryMovement::query()->where('inventory_item_id', $this->itemA->id)->firstOrFail();
        expect($movement->quantity_delta)->toBe(25)
            ->and($movement->reason)->toBe(InventoryMovementReason::InitialStock);
    });
});

it('accumulates on-hand across multiple adjustments', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/inventory/{$this->itemA->id}/adjust", [
            'location_id' => $this->locationA->id,
            'quantity_delta' => 10,
            'reason' => InventoryMovementReason::InitialStock->value,
        ], tenantHeader($this->storeA))
        ->assertOk();

    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/inventory/{$this->itemA->id}/adjust", [
            'location_id' => $this->locationA->id,
            'quantity_delta' => -4,
            'reason' => InventoryMovementReason::Damage->value,
        ], tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonPath('data.on_hand', 6);

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(InventoryMovement::query()->where('inventory_item_id', $this->itemA->id)->count())->toBe(2);
    });
});

it('rejects an adjustment that would drive on-hand negative', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/inventory/{$this->itemA->id}/adjust", [
            'location_id' => $this->locationA->id,
            'quantity_delta' => -1,
            'reason' => InventoryMovementReason::ManualAdjustment->value,
        ], tenantHeader($this->storeA))
        ->assertStatus(422);

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(InventoryMovement::query()->where('inventory_item_id', $this->itemA->id)->count())->toBe(0);
    });
});

it('rejects a zero quantity_delta', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/inventory/{$this->itemA->id}/adjust", [
            'location_id' => $this->locationA->id,
            'quantity_delta' => 0,
            'reason' => InventoryMovementReason::ManualAdjustment->value,
        ], tenantHeader($this->storeA))
        ->assertStatus(422);
});

it('rejects a Store B location id when adjusting a Store A item', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/inventory/{$this->itemA->id}/adjust", [
            'location_id' => $this->locationB->id,
            'quantity_delta' => 5,
            'reason' => InventoryMovementReason::InitialStock->value,
        ], tenantHeader($this->storeA))
        ->assertStatus(422);
});

it('does not let Store A adjust or list a Store B inventory item', function () {
    $variantResponse = $this->actingAs($this->userB, 'sanctum')
        ->postJson("/api/v1/products/{$this->productB->id}/variants", ['price_amount' => 500], tenantHeader($this->storeB))
        ->assertCreated();

    $itemB = app(TenantContext::class)->scope(
        $this->storeB,
        fn () => InventoryItem::query()->where('product_variant_id', $variantResponse->json('data.id'))->firstOrFail(),
    );

    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/inventory/{$itemB->id}/adjust", [
            'location_id' => $this->locationA->id,
            'quantity_delta' => 5,
            'reason' => InventoryMovementReason::InitialStock->value,
        ], tenantHeader($this->storeA))
        ->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/inventory', tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonMissing(['id' => $itemB->id]);

    expect(InventoryLevel::withoutGlobalScopes()->where('inventory_item_id', $itemB->id)->count())->toBe(0);
});
