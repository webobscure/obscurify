<?php

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Models\Collection;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\RefreshSearchSuggestions;
use App\Domain\Search\Models\SearchQuery;
use App\Domain\Search\Models\SearchSettings;
use App\Domain\Search\Support\SearchSuggestionEngine;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultSearchSetup::class)->handle($this->store);
    });
});

it('suggests matching products, collections, and categories for a prefix', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Running Shoes']);
        app(BuildSearchDocument::class)->handle($product->fresh());
        Collection::factory()->create(['title' => 'Running Gear']);
        Category::factory()->create(['title' => 'Running']);

        $suggestions = app(SearchSuggestionEngine::class)->suggest($this->store, 'runn');

        expect($suggestions['products'])->toHaveCount(1);
        expect($suggestions['products'][0]->title)->toBe('Running Shoes');
        expect($suggestions['collections'][0]['title'])->toBe('Running Gear');
        expect($suggestions['categories'][0]['title'])->toBe('Running');
    });
});

it('respects the store\'s configured autocomplete limit', function () {
    app(TenantContext::class)->scope($this->store, function () {
        foreach (range(1, 5) as $i) {
            app(BuildSearchDocument::class)->handle(Product::factory()->create(['title' => "Limited Item {$i}"])->fresh());
        }

        SearchSettings::query()->where('store_id', $this->store->id)->update(['autocomplete_limit' => 2]);

        $suggestions = app(SearchSuggestionEngine::class)->suggest($this->store, 'limited');

        expect($suggestions['products'])->toHaveCount(2);
    });
});

it('surfaces popular queries from the refreshed suggestion cache', function () {
    app(TenantContext::class)->scope($this->store, function () {
        foreach (range(1, 3) as $i) {
            SearchQuery::query()->create(['query_text' => 'shoes', 'normalized_query' => 'shoes', 'result_count' => 5]);
        }
        SearchQuery::query()->create(['query_text' => 'hats', 'normalized_query' => 'hats', 'result_count' => 2]);

        app(RefreshSearchSuggestions::class)->handle($this->store);

        $suggestions = app(SearchSuggestionEngine::class)->suggest($this->store, '');

        expect($suggestions['popular_queries'][0])->toBe('shoes');
    });
});

it('returns empty suggestions for an empty prefix, except popular queries', function () {
    app(TenantContext::class)->scope($this->store, function () {
        app(BuildSearchDocument::class)->handle(Product::factory()->create()->fresh());

        $suggestions = app(SearchSuggestionEngine::class)->suggest($this->store, '');

        expect($suggestions['products'])->toBe([]);
        expect($suggestions['collections'])->toBe([]);
        expect($suggestions['categories'])->toBe([]);
    });
});
