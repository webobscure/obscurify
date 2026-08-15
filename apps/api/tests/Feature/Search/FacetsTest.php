<?php

use App\Domain\Catalog\Application\AttachProductToCategory;
use App\Domain\Catalog\Application\CreateProductOption;
use App\Domain\Catalog\Application\CreateProductOptionValue;
use App\Domain\Catalog\Application\CreateProductVariant;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Collections\Application\AttachProductToCollection;
use App\Domain\Collections\Models\Collection;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\ExecuteSearch;
use App\Domain\Search\Support\ExecuteSearchRequest;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultSearchSetup::class)->handle($this->store);
    });
});

it('returns facet counts for vendor, product type, tags, availability, category, collection, and price', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $category = Category::factory()->create(['title' => 'Electronics']);
        $collection = Collection::factory()->create(['title' => 'Summer Sale', 'status' => 'active']);

        $productA = Product::factory()->create(['vendor' => 'Acme', 'product_type' => 'Gadget', 'tags' => ['sale']]);
        app(AttachProductToCategory::class)->handle($category, $productA);
        app(AttachProductToCollection::class)->handle($collection, $productA);
        ProductVariant::factory()->create(['product_id' => $productA->id, 'price_amount' => 2000]);
        app(BuildSearchDocument::class)->handle($productA->fresh());

        $productB = Product::factory()->create(['vendor' => 'Acme', 'product_type' => 'Widget']);
        ProductVariant::factory()->create(['product_id' => $productB->id, 'price_amount' => 8000]);
        app(BuildSearchDocument::class)->handle($productB->fresh());

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest);

        expect(collect($result->facets['vendor'])->firstWhere('value', 'Acme')['count'])->toBe(2);
        expect(collect($result->facets['product_type'])->pluck('value')->sort()->values()->all())->toBe(['Gadget', 'Widget']);
        expect(collect($result->facets['tags'])->firstWhere('value', 'sale')['count'])->toBe(1);
        expect(collect($result->facets['category'])->firstWhere('id', $category->id)['label'])->toBe('Electronics');
        expect(collect($result->facets['category'])->firstWhere('id', $category->id)['count'])->toBe(1);
        expect(collect($result->facets['collection'])->firstWhere('id', $collection->id)['label'])->toBe('Summer Sale');
        expect($result->facets['price']['min'])->toBe(2000);
        expect($result->facets['price']['max'])->toBe(8000);
    });
});

it('returns facet counts for variant options', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create();
        $option = app(CreateProductOption::class)->handle($product, ['name' => 'Color']);
        $red = app(CreateProductOptionValue::class)->handle($option, ['value' => 'Red']);
        app(CreateProductVariant::class)->handle($product->fresh(), ['price_amount' => 1000, 'option_value_ids' => [$red->id]]);
        app(BuildSearchDocument::class)->handle($product->fresh());

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest);

        $colorFacet = collect($result->facets['variant_options'])->firstWhere('option', 'Color');
        expect($colorFacet['value'])->toBe('Red');
        expect($colorFacet['count'])->toBe(1);
    });
});

it('reflects a store with no facet-eligible fields as empty facet lists, not an error', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest);

        expect($result->facets['vendor'])->toBe([]);
        expect($result->facets['category'])->toBe([]);
        expect($result->facets['price']['min'])->toBeNull();
    });
});
