<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

function productInStore(Store $store, array $overrides = []): Product
{
    return app(TenantContext::class)->scope(
        $store,
        fn () => Product::factory()->create($overrides),
    );
}

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    $this->productA = productInStore($this->storeA, ['title' => 'Product A']);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    $this->productB = productInStore($this->storeB, ['title' => 'Product B']);
});

it('lets Store A user list Store A products', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/products', tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->productA->id)
        ->assertJsonCount(1, 'data');
});

it('never returns Store B products in Store A index', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/products', tenantHeader($this->storeA));

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->not->toContain($this->productB->id);
});

it('lets Store A user GET a Store A product', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/products/{$this->productA->id}", tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonPath('data.id', $this->productA->id);
});

it('does not let Store A user GET a Store B product (route model binding)', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/products/{$this->productB->id}", tenantHeader($this->storeA))
        ->assertNotFound();
});

it('does not let Store A user PATCH a Store B product', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->patchJson("/api/v1/products/{$this->productB->id}", ['title' => 'Hacked'], tenantHeader($this->storeA))
        ->assertNotFound();

    expect($this->productB->fresh()->title)->toBe('Product B');
});

it('does not let Store A user DELETE a Store B product', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->deleteJson("/api/v1/products/{$this->productB->id}", [], tenantHeader($this->storeA))
        ->assertNotFound();

    expect(Product::withoutGlobalScopes()->find($this->productB->id))->not->toBeNull();
});

it('lets Store A user update and delete their own product', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->patchJson("/api/v1/products/{$this->productA->id}", ['title' => 'Updated A'], tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated A');

    $this->actingAs($this->userA, 'sanctum')
        ->deleteJson("/api/v1/products/{$this->productA->id}", [], tenantHeader($this->storeA))
        ->assertNoContent();

    expect(Product::withoutGlobalScopes()->find($this->productA->id)->deleted_at)->not->toBeNull();
});

it('always assigns the active tenant store even if the payload spoofs store_id', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->postJson('/api/v1/products', [
            'store_id' => $this->storeB->id,
            'title' => 'Hack',
        ], tenantHeader($this->storeA));

    $response->assertCreated()->assertJsonPath('data.store_id', $this->storeA->id);

    $created = Product::withoutGlobalScopes()->findOrFail($response->json('data.id'));
    expect($created->store_id)->toBe($this->storeA->id)
        ->and($created->store_id)->not->toBe($this->storeB->id);
});

it('fails closed when no active tenant is selected', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/products')
        ->assertStatus(428);

    $this->actingAs($this->userA, 'sanctum')
        ->postJson('/api/v1/products', ['title' => 'No Tenant'])
        ->assertStatus(428);
});

it('fails closed for a completely unauthenticated request', function () {
    $this->getJson('/api/v1/products')->assertUnauthorized();
});

it('rejects the tenant header for a store the user is not a member of', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/products', tenantHeader($this->storeB))
        ->assertForbidden();
});
