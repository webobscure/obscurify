<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionProduct;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->productA = app(TenantContext::class)->scope($this->storeA, fn () => Product::factory()->create());
    $this->collectionA = app(TenantContext::class)->scope($this->storeA, fn () => Collection::factory()->create());

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    $this->productB = app(TenantContext::class)->scope($this->storeB, fn () => Product::factory()->create());
    $this->collectionB = app(TenantContext::class)->scope($this->storeB, fn () => Collection::factory()->create());
});

it('adds and removes a product from a collection', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/collections/{$this->collectionA->id}/products/{$this->productA->id}", [], tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonCount(1, 'data.products');

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(CollectionProduct::query()->where('collection_id', $this->collectionA->id)->where('product_id', $this->productA->id)->exists())->toBeTrue();
    });

    $this->actingAs($this->userA, 'sanctum')
        ->deleteJson("/api/v1/collections/{$this->collectionA->id}/products/{$this->productA->id}", [], tenantHeader($this->storeA))
        ->assertNoContent();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(CollectionProduct::query()->where('collection_id', $this->collectionA->id)->where('product_id', $this->productA->id)->exists())->toBeFalse();
    });
});

it('is idempotent when attaching the same product twice', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/collections/{$this->collectionA->id}/products/{$this->productA->id}", [], tenantHeader($this->storeA))
        ->assertOk();

    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/collections/{$this->collectionA->id}/products/{$this->productA->id}", [], tenantHeader($this->storeA))
        ->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(CollectionProduct::query()->where('collection_id', $this->collectionA->id)->count())->toBe(1);
    });
});

it('cannot add a Store B product to a Store A collection', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/collections/{$this->collectionA->id}/products/{$this->productB->id}", [], tenantHeader($this->storeA))
        ->assertNotFound();

    expect(CollectionProduct::withoutGlobalScopes()->where('collection_id', $this->collectionA->id)->count())->toBe(0);
});

it('cannot add a product to a Store B collection while Store A is active', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/v1/collections/{$this->collectionB->id}/products/{$this->productA->id}", [], tenantHeader($this->storeA))
        ->assertNotFound();
});

it('does not let Store A read, update or delete a Store B collection', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/collections/{$this->collectionB->id}", tenantHeader($this->storeA))
        ->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')
        ->patchJson("/api/v1/collections/{$this->collectionB->id}", ['title' => 'Hacked'], tenantHeader($this->storeA))
        ->assertNotFound();

    $this->actingAs($this->userA, 'sanctum')
        ->deleteJson("/api/v1/collections/{$this->collectionB->id}", [], tenantHeader($this->storeA))
        ->assertNotFound();

    expect(Collection::withoutGlobalScopes()->find($this->collectionB->id)->title)->not->toBe('Hacked');
});
