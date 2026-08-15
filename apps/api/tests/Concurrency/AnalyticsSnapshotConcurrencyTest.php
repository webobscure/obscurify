<?php

use App\Domain\Analytics\Models\AnalyticsSnapshot;
use App\Domain\Analytics\Support\AnalyticsAggregator;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Same fork-based real-concurrency pattern as
 * CustomerGroupMembershipConcurrencyTest (M18) and
 * WorkflowExecutionConcurrencyTest (M19). Proves
 * AnalyticsAggregator::upsert()'s catch-and-retry actually prevents the
 * race it exists to prevent: `updateOrCreate()` alone is a SELECT then
 * INSERT-or-UPDATE, not atomic — two workers aggregating the same
 * (store, metric, day) at once (e.g. two `outbox:process` runs, each
 * having claimed a different event that both land on today) could both
 * see "no row yet" and both attempt to INSERT, and the loser would hit
 * `analytics_snapshots`' unique constraint as a hard failure rather
 * than a graceful retry-as-update.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets two simultaneous aggregation runs for the same store and day both succeed without error, leaving exactly one snapshot row per metric', function () {
    $storeId = $this->store->id;
    $day = now();

    $aggregate = function () use ($storeId, $day) {
        return app(TenantContext::class)->scope(Store::query()->find($storeId), function () use ($storeId, $day) {
            app(AnalyticsAggregator::class)->aggregateDay($storeId, $day);

            return true;
        });
    };

    $results = runConcurrently([$aggregate, $aggregate]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    expect($succeeded)->toHaveCount(2);

    app(TenantContext::class)->scope($this->store, function () use ($day) {
        expect(AnalyticsSnapshot::query()->where('metric_key', 'gross_revenue')->where('period_date', $day->toDateString())->count())->toBe(1);
        expect(AnalyticsSnapshot::query()->where('period_date', $day->toDateString())->count())->toBe(22);
    });
});
