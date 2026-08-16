<?php

use App\Domain\Catalog\Application\AttachProductToCategory;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Collections\Application\AttachProductToCollection;
use App\Domain\Collections\Models\Collection;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-graphql-catalog.localhost';
    domainForStore($this->store, $this->host);
});

function scopedCatalog(Store $store, Closure $fn): mixed
{
    return app(TenantContext::class)->scope($store, $fn);
}

it('resolves the store query for the tenant matching the request hostname', function () {
    $response = graphqlRequest($this->host, 'query { store { id name defaultCurrency } }');

    $response->assertOk();
    expect($response->json('data.store.id'))->toBe($this->store->id);
    expect($response->json('data.store.name'))->toBe($this->store->name);
});

it('lists active products only, with price and variant fields', function () {
    scopedCatalog($this->store, function () {
        $active = Product::factory()->create(['title' => 'Active Widget', 'status' => 'active']);
        ProductVariant::factory()->create(['product_id' => $active->id, 'price_amount' => 1500, 'status' => 'active']);
        Product::factory()->create(['title' => 'Draft Widget', 'status' => 'draft']);
    });

    $response = graphqlRequest($this->host, 'query { products { data { title price { amount currency } } pageInfo { total } } }');

    $response->assertOk();
    $titles = collect($response->json('data.products.data'))->pluck('title')->all();
    expect($titles)->toBe(['Active Widget']);
    expect($response->json('data.products.data.0.price.amount'))->toBe(1500);
});

it('resolves a single product by slug, 404-equivalent GraphQL error for an unknown slug', function () {
    scopedCatalog($this->store, fn () => Product::factory()->create(['title' => 'Findable', 'slug' => 'findable', 'status' => 'active']));

    $found = graphqlRequest($this->host, 'query { product(slug: "findable") { title } }');
    $found->assertOk();
    expect($found->json('data.product.title'))->toBe('Findable');

    $missing = graphqlRequest($this->host, 'query { product(slug: "nope") { title } }');
    $missing->assertOk();
    expect($missing->json('data.product'))->toBeNull();
    expect($missing->json('errors.0.message'))->toBe('Product not found.');
});

it('filters products by collection and category slug', function () {
    scopedCatalog($this->store, function () {
        $collection = Collection::factory()->create(['title' => 'Summer', 'slug' => 'summer', 'status' => 'active']);
        $category = Category::factory()->create(['title' => 'Shoes', 'slug' => 'shoes']);
        $inCollection = Product::factory()->create(['title' => 'In Collection', 'status' => 'active']);
        $inCategory = Product::factory()->create(['title' => 'In Category', 'status' => 'active']);
        Product::factory()->create(['title' => 'In Neither', 'status' => 'active']);

        app(AttachProductToCollection::class)->handle($collection, $inCollection);
        app(AttachProductToCategory::class)->handle($category, $inCategory);
    });

    $byCollection = graphqlRequest($this->host, 'query { products(collection: "summer") { data { title } } }');
    expect(collect($byCollection->json('data.products.data'))->pluck('title')->all())->toBe(['In Collection']);

    $byCategory = graphqlRequest($this->host, 'query { products(category: "shoes") { data { title } } }');
    expect(collect($byCategory->json('data.products.data'))->pluck('title')->all())->toBe(['In Category']);
});

it('resolves collections and a single collection by slug', function () {
    scopedCatalog($this->store, fn () => Collection::factory()->create(['title' => 'Winter', 'slug' => 'winter', 'status' => 'active']));

    $list = graphqlRequest($this->host, 'query { collections { data { title slug } } }');
    expect(collect($list->json('data.collections.data'))->pluck('slug')->all())->toBe(['winter']);

    $single = graphqlRequest($this->host, 'query { collection(slug: "winter") { title } }');
    expect($single->json('data.collection.title'))->toBe('Winter');
});

it('resolves the category tree with nested children', function () {
    scopedCatalog($this->store, function () {
        $parent = Category::factory()->create(['title' => 'Apparel', 'slug' => 'apparel', 'parent_id' => null, 'position' => 0]);
        Category::factory()->create(['title' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $parent->id, 'position' => 0]);
    });

    $response = graphqlRequest($this->host, 'query { categories { title children { title } } }');

    $response->assertOk();
    expect($response->json('data.categories.0.title'))->toBe('Apparel');
    expect($response->json('data.categories.0.children.0.title'))->toBe('Shirts');
});

it('never leaks another store\'s products, collections, or categories through GraphQL', function () {
    $otherUser = User::factory()->create();
    $otherStore = createStoreForUser($otherUser);
    $otherHost = 'e2e-graphql-other.localhost';
    domainForStore($otherStore, $otherHost);

    scopedCatalog($otherStore, function () {
        Product::factory()->create(['title' => 'Other Store Product', 'slug' => 'other-store-product', 'status' => 'active']);
        Collection::factory()->create(['title' => 'Other Collection', 'slug' => 'other-collection', 'status' => 'active']);
    });

    $products = graphqlRequest($this->host, 'query { products { data { title } } }');
    expect(collect($products->json('data.products.data'))->pluck('title')->all())->not->toContain('Other Store Product');

    $product = graphqlRequest($this->host, 'query { product(slug: "other-store-product") { title } }');
    expect($product->json('data.product'))->toBeNull();

    $collection = graphqlRequest($this->host, 'query { collection(slug: "other-collection") { title } }');
    expect($collection->json('data.collection'))->toBeNull();
});
