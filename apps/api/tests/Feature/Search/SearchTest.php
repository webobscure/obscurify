<?php

use App\Domain\Catalog\Application\AttachProductToCategory;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\ExecuteSearch;
use App\Domain\Search\Enums\SearchSortOption;
use App\Domain\Search\Models\SearchDocument;
use App\Domain\Search\Support\ExecuteSearchRequest;
use App\Domain\Search\Support\SearchFilters;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultSearchSetup::class)->handle($this->store);
    });
});

function indexProduct(Product $product): void
{
    app(BuildSearchDocument::class)->handle($product->fresh());
}

it('finds a product by full-text match', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Blue Running Shoes']);
        indexProduct($product);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'running shoes'));

        expect($result->total)->toBe(1);
        expect($result->items[0]->productId)->toBe($product->id);
    });
});

it('finds a product by prefix match, ranked above a mere contains match', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $prefixMatch = Product::factory()->create(['title' => 'Running Shoes']);
        $containsMatch = Product::factory()->create(['title' => 'Trail Running Gear']);
        indexProduct($prefixMatch);
        indexProduct($containsMatch);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'running'));

        expect($result->total)->toBe(2);
        expect($result->items[0]->productId)->toBe($prefixMatch->id);
    });
});

it('is case-insensitive', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Wireless Keyboard']);
        indexProduct($product);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'WIRELESS keyboard'));

        expect($result->total)->toBe(1);
    });
});

it('is accent-insensitive', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Café Table']);
        indexProduct($product);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'cafe table'));

        expect($result->total)->toBe(1);
    });
});

it('offers simple typo tolerance when the exact query has zero results', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Bluetooth Speaker']);
        indexProduct($product);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'bluetoth speaker'));

        expect($result->total)->toBe(1);
    });
});

it('returns zero results for a genuinely unmatched query, without a false-positive correction', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Bluetooth Speaker']);
        indexProduct($product);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'xyzxyzxyz'));

        expect($result->total)->toBe(0);
    });
});

it('excludes draft and archived products from search results', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $draft = Product::factory()->create(['title' => 'Draft Widget', 'status' => 'draft']);
        $archived = Product::factory()->create(['title' => 'Archived Widget', 'status' => 'archived']);
        indexProduct($draft);
        indexProduct($archived);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'widget'));

        expect($result->total)->toBe(0);
    });
});

it('paginates results', function () {
    app(TenantContext::class)->scope($this->store, function () {
        foreach (range(1, 5) as $i) {
            indexProduct(Product::factory()->create(['title' => "Paginated Item {$i}"]));
        }

        $page1 = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'paginated', page: 1, perPage: 2));
        $page2 = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'paginated', page: 2, perPage: 2));

        expect($page1->total)->toBe(5);
        expect($page1->items)->toHaveCount(2);
        expect($page2->items)->toHaveCount(2);
        expect($page1->items[0]->productId)->not->toBe($page2->items[0]->productId);
    });
});

it('filters by category, collection, vendor, product type, tags, and price range', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $category = Category::factory()->create();
        $matching = Product::factory()->create(['title' => 'Filter Match', 'vendor' => 'Acme', 'product_type' => 'Gadget', 'tags' => ['new']]);
        app(AttachProductToCategory::class)->handle($category, $matching);
        ProductVariant::factory()->create(['product_id' => $matching->id, 'price_amount' => 5000]);
        indexProduct($matching);

        $nonMatching = Product::factory()->create(['title' => 'Filter Nomatch', 'vendor' => 'Other']);
        indexProduct($nonMatching);

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(
            filters: new SearchFilters(categoryIds: [$category->id], vendors: ['Acme'], productTypes: ['Gadget'], tags: ['new'], priceMin: 1000, priceMax: 10000),
        ));

        expect($result->total)->toBe(1);
        expect($result->items[0]->productId)->toBe($matching->id);
    });
});

it('sorts by newest, oldest, price ascending, price descending, and best selling', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $cheap = Product::factory()->create(['title' => 'Sortable A', 'created_at' => now()->subDays(2)]);
        ProductVariant::factory()->create(['product_id' => $cheap->id, 'price_amount' => 1000]);
        indexProduct($cheap);

        $expensive = Product::factory()->create(['title' => 'Sortable B', 'created_at' => now()->subDay()]);
        ProductVariant::factory()->create(['product_id' => $expensive->id, 'price_amount' => 9000]);
        indexProduct($expensive);

        SearchDocument::query()->where('product_id', $expensive->id)->update(['sales_count' => 50]);

        $newest = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'sortable', sort: SearchSortOption::Newest));
        expect($newest->items[0]->productId)->toBe($expensive->id);

        $oldest = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'sortable', sort: SearchSortOption::Oldest));
        expect($oldest->items[0]->productId)->toBe($cheap->id);

        $priceAsc = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'sortable', sort: SearchSortOption::PriceAsc));
        expect($priceAsc->items[0]->productId)->toBe($cheap->id);

        $priceDesc = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'sortable', sort: SearchSortOption::PriceDesc));
        expect($priceDesc->items[0]->productId)->toBe($expensive->id);

        $bestSelling = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'sortable', sort: SearchSortOption::BestSelling));
        expect($bestSelling->items[0]->productId)->toBe($expensive->id);
    });
});
