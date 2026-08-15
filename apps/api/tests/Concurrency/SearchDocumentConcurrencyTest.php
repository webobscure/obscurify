<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Application\BuildSearchDocument;
use App\Domain\Search\Models\SearchDocument;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Same fork-based real-concurrency pattern as
 * AnalyticsSnapshotConcurrencyTest (M20) and
 * NotificationDeliveryConcurrencyTest (M21). Proves
 * BuildSearchDocument::upsert()'s catch-and-retry actually prevents the
 * race it exists to prevent: `updateOrCreate()` alone is a SELECT then
 * INSERT-or-UPDATE, not atomic — two workers reindexing the same
 * product at once (e.g. a PriceChanged and a VariantUpdated event
 * landing on the queue nearly simultaneously) could both see "no row
 * yet" and both attempt to INSERT, and the loser would hit
 * `search_documents`' own (store_id, product_id) unique constraint as
 * a hard failure rather than a graceful retry-as-update.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->product = app(TenantContext::class)->scope($this->store, fn () => Product::factory()->create(['title' => 'Concurrent Product']));
});

afterEach(function () {
    DB::table('products')->where('id', $this->product->id)->delete();
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets two simultaneous BuildSearchDocument runs for the same product both succeed without error, leaving exactly one SearchDocument row', function () {
    $storeId = $this->store->id;
    $productId = $this->product->id;

    $build = function () use ($storeId, $productId) {
        return app(TenantContext::class)->scope(Store::query()->find($storeId), function () use ($productId) {
            $product = Product::query()->find($productId);
            app(BuildSearchDocument::class)->handle($product);

            return true;
        });
    };

    $results = runConcurrently([$build, $build]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    expect($succeeded)->toHaveCount(2);

    app(TenantContext::class)->scope($this->store, function () use ($productId) {
        expect(SearchDocument::query()->where('product_id', $productId)->count())->toBe(1);
    });
});
