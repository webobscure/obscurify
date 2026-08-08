<?php

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Enums\CollectionStatus;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionProduct;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    domainForStore($this->storeB, 'store-b.localhost');
});

it('shows an active collection with only its active products', function () {
    [$collection, $activeProduct] = app(TenantContext::class)->scope($this->storeA, function () {
        $collection = Collection::factory()->create(['slug' => 'featured', 'status' => CollectionStatus::Active]);
        $active = Product::factory()->create(['title' => 'Active', 'status' => ProductStatus::Active]);
        $draft = Product::factory()->create(['title' => 'Draft', 'status' => ProductStatus::Draft]);
        CollectionProduct::factory()->create(['collection_id' => $collection->id, 'product_id' => $active->id]);
        CollectionProduct::factory()->create(['collection_id' => $collection->id, 'product_id' => $draft->id]);

        return [$collection, $active];
    });

    $response = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/collections/featured'))
        ->assertOk()
        ->assertJsonPath('data.id', $collection->id);

    expect(collect($response->json('products.data'))->pluck('title')->all())->toBe(['Active']);
});

it('hides a draft collection from the storefront', function () {
    app(TenantContext::class)->scope($this->storeA, fn () => Collection::factory()->create(['slug' => 'hidden', 'status' => CollectionStatus::Draft]));

    $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/collections/hidden'))
        ->assertNotFound();
});

it('never exposes a Store B product through a Store A collection', function () {
    $productB = app(TenantContext::class)->scope($this->storeB, fn () => Product::factory()->create(['status' => ProductStatus::Active]));

    app(TenantContext::class)->scope($this->storeA, function () {
        $collection = Collection::factory()->create(['slug' => 'cross', 'status' => CollectionStatus::Active]);
        $productA = Product::factory()->create(['status' => ProductStatus::Active]);
        CollectionProduct::factory()->create(['collection_id' => $collection->id, 'product_id' => $productA->id]);
    });

    $response = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/collections/cross'))->assertOk();

    expect(collect($response->json('products.data'))->pluck('id'))->not->toContain($productB->id);
});
