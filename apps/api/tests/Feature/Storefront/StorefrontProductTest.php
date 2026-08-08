<?php

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCategory;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Locations\Models\Location;
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

function scopedA(Closure $fn)
{
    return app(TenantContext::class)->scope(test()->storeA, $fn);
}

it('only lists active products for the resolved store', function () {
    scopedA(fn () => Product::factory()->create(['title' => 'Active', 'status' => ProductStatus::Active]));
    scopedA(fn () => Product::factory()->create(['title' => 'Draft', 'status' => ProductStatus::Draft]));
    scopedA(fn () => Product::factory()->create(['title' => 'Archived', 'status' => ProductStatus::Archived]));
    $deleted = scopedA(fn () => Product::factory()->create(['title' => 'Deleted', 'status' => ProductStatus::Active]));
    scopedA(fn () => $deleted->delete());

    $response = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/products'))->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Active']);
});

it('never lists Store B products on Store A host', function () {
    scopedA(fn () => Product::factory()->create(['status' => ProductStatus::Active]));
    app(TenantContext::class)->scope($this->storeB, fn () => Product::factory()->create(['title' => 'B product', 'status' => ProductStatus::Active]));

    $response = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/products'))->assertOk();

    expect(collect($response->json('data'))->pluck('title'))->not->toContain('B product');
});

it('shows an active product by slug within the resolved store only', function () {
    $product = scopedA(fn () => Product::factory()->create(['slug' => 'my-product', 'status' => ProductStatus::Active]));

    $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/products/my-product'))
        ->assertOk()
        ->assertJsonPath('data.id', $product->id);

    $this->getJson(storefrontUrl('store-b.localhost', '/api/v1/storefront/products/my-product'))
        ->assertNotFound();
});

it('never shows a draft product by slug', function () {
    scopedA(fn () => Product::factory()->create(['slug' => 'hidden', 'status' => ProductStatus::Draft]));

    $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/products/hidden'))
        ->assertNotFound();
});

it('does not expose cost_amount or internal fields on variants', function () {
    $product = scopedA(fn () => Product::factory()->create(['slug' => 'lean', 'status' => ProductStatus::Active]));
    scopedA(fn () => ProductVariant::factory()->create([
        'product_id' => $product->id,
        'status' => ProductStatus::Active,
        'cost_amount' => 500,
    ]));

    $response = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/products/lean'))->assertOk();

    $variant = $response->json('data.variants.0');
    expect($variant)->not->toHaveKey('cost_amount')
        ->and($variant)->not->toHaveKey('store_id')
        ->and($variant)->not->toHaveKey('status');
});

it('reports availability from inventory levels', function () {
    [$product, $variant] = scopedA(function () {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'status' => ProductStatus::Active]);
        $item = InventoryItem::factory()->create(['product_variant_id' => $variant->id, 'tracked' => true]);
        $location = Location::factory()->create();
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 10, 'reserved' => 3]);

        return [$product, $variant];
    });

    $response = $this->getJson(storefrontUrl('store-a.localhost', "/api/v1/storefront/products/{$product->slug}"))->assertOk();

    expect($response->json('data.variants.0.availability'))->toBe([
        'tracked' => true,
        'available' => 7,
        'in_stock' => true,
    ]);
});

it('treats an untracked variant as unlimited availability', function () {
    [$product] = scopedA(function () {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'status' => ProductStatus::Active]);
        InventoryItem::factory()->create(['product_variant_id' => $variant->id, 'tracked' => false]);

        return [$product];
    });

    $response = $this->getJson(storefrontUrl('store-a.localhost', "/api/v1/storefront/products/{$product->slug}"))->assertOk();

    expect($response->json('data.variants.0.availability'))->toBe([
        'tracked' => false,
        'available' => null,
        'in_stock' => true,
    ]);
});

it('filters the listing by category slug', function () {
    $category = scopedA(fn () => Category::factory()->create(['slug' => 'shirts']));
    $inCategory = scopedA(fn () => Product::factory()->create(['title' => 'In category', 'status' => ProductStatus::Active]));
    scopedA(fn () => ProductCategory::factory()->create(['product_id' => $inCategory->id, 'category_id' => $category->id]));
    scopedA(fn () => Product::factory()->create(['title' => 'Not in category', 'status' => ProductStatus::Active]));

    $response = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/products?category=shirts'))->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['In category']);
});

it('sorts the listing by price', function () {
    scopedA(function () {
        $cheap = Product::factory()->create(['title' => 'Cheap', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $cheap->id, 'status' => ProductStatus::Active, 'price_amount' => 100]);

        $pricey = Product::factory()->create(['title' => 'Pricey', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $pricey->id, 'status' => ProductStatus::Active, 'price_amount' => 900]);
    });

    $asc = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/products?sort=price_asc'))->assertOk();
    expect(collect($asc->json('data'))->pluck('title')->all())->toBe(['Cheap', 'Pricey']);

    $desc = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/products?sort=price_desc'))->assertOk();
    expect(collect($desc->json('data'))->pluck('title')->all())->toBe(['Pricey', 'Cheap']);
});
