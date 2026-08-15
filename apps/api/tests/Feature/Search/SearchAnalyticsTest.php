<?php

use App\Domain\Analytics\Application\RegisterBuiltInAnalyticsCatalog;
use App\Domain\Analytics\Models\AnalyticsSnapshot;
use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\ExecuteSearch;
use App\Domain\Search\Application\RecordSearchClick;
use App\Domain\Search\Models\SearchQuery;
use App\Domain\Search\Support\ExecuteSearchRequest;
use App\Domain\Search\Support\SearchAnalyticsSummary;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultSearchSetup::class)->handle($this->store);
    });
    app(RegisterBuiltInAnalyticsCatalog::class)->handle();
});

function processSearchOutboxFor(Store $store): void
{
    Artisan::call('outbox:process');
}

it('logs every search as a SearchQuery row, including zero-result searches', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Blue Jacket']);
        app(BuildSearchDocument::class)->handle($product->fresh());

        app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'jacket'));
        app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'nonexistentthing'));

        expect(SearchQuery::query()->count())->toBe(2);
        expect(SearchQuery::query()->where('normalized_query', 'nonexistentthing')->first()->result_count)->toBe(0);
    });
});

it('records a click event and surfaces it in the analytics summary CTR', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Red Sneakers']);
        app(BuildSearchDocument::class)->handle($product->fresh());

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'sneakers'));
        $searchQuery = $result->searchQuery;

        app(RecordSearchClick::class)->handle($searchQuery, $product->id, 0);

        $summary = app(SearchAnalyticsSummary::class)->build($this->store->id, now()->subDay(), now()->addDay());

        expect($summary['total_searches'])->toBe(1);
        expect($summary['total_clicks'])->toBe(1);
        expect($summary['click_through_rate'])->toBe(1.0);
    });
});

it('surfaces the most popular and top zero-result searches', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Green Hat']);
        app(BuildSearchDocument::class)->handle($product->fresh());

        foreach (range(1, 3) as $i) {
            app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'hat'));
        }
        app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'totallymissing'));

        $summary = app(SearchAnalyticsSummary::class)->build($this->store->id, now()->subDay(), now()->addDay());

        expect($summary['popular_searches'][0])->toBe(['query' => 'hat', 'count' => 3]);
        expect(collect($summary['zero_result_searches'])->pluck('query')->all())->toBe(['totallymissing']);
    });
});

it('projects SearchPerformed and SearchResultClicked into the Analytics Platform metrics', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $product = Product::factory()->create(['title' => 'Purple Scarf']);
        app(BuildSearchDocument::class)->handle($product->fresh());

        $result = app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'scarf'));
        app(ExecuteSearch::class)->handle($this->store, new ExecuteSearchRequest(queryText: 'notfoundatall'));
        app(RecordSearchClick::class)->handle($result->searchQuery, $product->id, 0);
    });

    processSearchOutboxFor($this->store);

    app(TenantContext::class)->scope($this->store, function () {
        $day = Carbon::today()->toDateString();

        $searchCount = AnalyticsSnapshot::query()
            ->where('metric_key', 'search_count')->where('period_date', $day)->first();
        $zeroResultCount = AnalyticsSnapshot::query()
            ->where('metric_key', 'zero_result_search_count')->where('period_date', $day)->first();
        $clickCount = AnalyticsSnapshot::query()
            ->where('metric_key', 'search_click_count')->where('period_date', $day)->first();

        expect((int) $searchCount->value)->toBe(2);
        expect((int) $zeroResultCount->value)->toBe(1);
        expect((int) $clickCount->value)->toBe(1);
    });
});
