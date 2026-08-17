<?php

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Models\Collection;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

function productWith(object $store, array $overrides = []): Product
{
    return app(TenantContext::class)->scope($store, fn () => Product::factory()->create($overrides));
}

it('filters products by search across title', function () {
    productWith($this->store, ['title' => 'Blue T-Shirt']);
    productWith($this->store, ['title' => 'Red Hoodie']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/products?search=Blue', tenantHeader($this->store))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Blue T-Shirt']);
});

it('filters products by status', function () {
    productWith($this->store, ['title' => 'Active One', 'status' => ProductStatus::Active]);
    productWith($this->store, ['title' => 'Draft One', 'status' => ProductStatus::Draft]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/products?status=draft', tenantHeader($this->store))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Draft One']);
});

it('filters products by vendor and product_type', function () {
    productWith($this->store, ['title' => 'Acme Widget', 'vendor' => 'Acme', 'product_type' => 'Widget']);
    productWith($this->store, ['title' => 'Other Gadget', 'vendor' => 'Other', 'product_type' => 'Gadget']);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/products?vendor=Acme', tenantHeader($this->store))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Acme Widget');

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/products?product_type=Gadget', tenantHeader($this->store))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Other Gadget');
});

it('filters products by collection_id', function () {
    $inCollection = productWith($this->store, ['title' => 'In Collection']);
    productWith($this->store, ['title' => 'Not In Collection']);

    $collection = app(TenantContext::class)->scope($this->store, fn () => Collection::factory()->create());

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/collections/{$collection->id}/products/{$inCollection->id}", [], tenantHeader($this->store))
        ->assertOk();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/products?collection_id={$collection->id}", tenantHeader($this->store))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['In Collection']);
});

it('sorts products by title ascending', function () {
    productWith($this->store, ['title' => 'Zebra']);
    productWith($this->store, ['title' => 'Apple']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/products?sort=title', tenantHeader($this->store))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Apple', 'Zebra']);
});

it('respects a custom per_page, clamped to 100', function () {
    app(TenantContext::class)->scope($this->store, fn () => Product::factory()->count(3)->create());

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/products?per_page=2', tenantHeader($this->store))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.per_page'))->toBe(2);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/products?per_page=999', tenantHeader($this->store))
        ->assertStatus(422);
});

it('combines filters and never leaks another store\'s products through them', function () {
    $otherUser = User::factory()->create();
    $otherStore = createStoreForUser($otherUser);
    productWith($otherStore, ['title' => 'Blue From Other Store', 'status' => ProductStatus::Active]);
    productWith($this->store, ['title' => 'Blue From My Store', 'status' => ProductStatus::Active]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/products?search=Blue&status=active', tenantHeader($this->store))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Blue From My Store']);
});
