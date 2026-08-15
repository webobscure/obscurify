<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\ExecuteSearch;
use App\Domain\Search\Models\PinnedSearchResult;
use App\Domain\Search\Models\SearchDocument;
use App\Domain\Search\Models\SearchRule;
use App\Domain\Search\Models\SearchSettings;
use App\Domain\Search\Models\SearchSynonym;
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

it('expands a query using a uni-directional synonym', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Samsung Television']);
        app(BuildSearchDocument::class)->handle($product->fresh());

        SearchSynonym::query()->create(['term' => 'tv', 'synonyms' => ['television']]);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'tv'));

        expect($result->total)->toBe(1);
    });
});

it('expands both directions for a bidirectional synonym', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $laptop = Product::factory()->create(['title' => 'Gaming Laptop']);
        app(BuildSearchDocument::class)->handle($laptop->fresh());

        SearchSynonym::query()->create(['term' => 'notebook', 'synonyms' => ['laptop'], 'is_bidirectional' => true]);

        expect(app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'notebook'))->total)->toBe(1);
        expect(app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'laptop'))->total)->toBe(1);
    });
});

it('does not expand synonyms when disabled in settings', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Samsung Television']);
        app(BuildSearchDocument::class)->handle($product->fresh());
        SearchSynonym::query()->create(['term' => 'tv', 'synonyms' => ['television']]);
        SearchSettings::query()->where('store_id', $this->store->id)->update(['synonyms_enabled' => false]);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'tv'));

        expect($result->total)->toBe(0);
    });
});

it('always shows a pinned product first for its exact keyword, regardless of relevance', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $relevant = Product::factory()->create(['title' => 'Best Selling Shoes']);
        app(BuildSearchDocument::class)->handle($relevant->fresh());
        SearchDocument::query()->where('product_id', $relevant->id)->update(['sales_count' => 1000]);

        $pinned = Product::factory()->create(['title' => 'Irrelevant Item']);
        app(BuildSearchDocument::class)->handle($pinned->fresh());

        PinnedSearchResult::query()->create(['keyword' => 'shoes', 'product_id' => $pinned->id, 'position' => 0]);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'shoes'));

        expect($result->items[0]->productId)->toBe($pinned->id);
        expect($result->items[0]->isPinned)->toBeTrue();
    });
});

it('hides a product matched by a hide rule for a specific keyword', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $hidden = Product::factory()->create(['title' => 'Discontinued Widget']);
        app(BuildSearchDocument::class)->handle($hidden->fresh());
        $visible = Product::factory()->create(['title' => 'Available Widget']);
        app(BuildSearchDocument::class)->handle($visible->fresh());

        SearchRule::query()->create(['name' => 'Hide discontinued', 'keyword' => 'widget', 'action' => 'hide', 'product_id' => $hidden->id]);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'widget'));

        expect($result->total)->toBe(1);
        expect(collect($result->items)->pluck('productId')->all())->toBe([$visible->id]);
    });
});

it('boosts a product above a more textually relevant one via a boost rule', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $moreRelevant = Product::factory()->create(['title' => 'Wireless Headphones']);
        app(BuildSearchDocument::class)->handle($moreRelevant->fresh());
        $boosted = Product::factory()->create(['title' => 'Bluetooth Earbuds Wireless']);
        app(BuildSearchDocument::class)->handle($boosted->fresh());

        SearchRule::query()->create(['name' => 'Boost earbuds', 'keyword' => 'wireless', 'action' => 'boost', 'product_id' => $boosted->id, 'boost_amount' => 1000]);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'wireless'));

        expect($result->items[0]->productId)->toBe($boosted->id);
    });
});

it('applies a global rule with no keyword to every search', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $hidden = Product::factory()->create(['title' => 'Global Hidden Product']);
        app(BuildSearchDocument::class)->handle($hidden->fresh());

        SearchRule::query()->create(['name' => 'Always hide', 'keyword' => null, 'action' => 'hide', 'product_id' => $hidden->id]);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'global hidden'));

        expect($result->total)->toBe(0);
    });
});
