<?php

namespace App\Domain\Analytics\Support;

use App\Domain\Stores\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Builds/rebuilds AnalyticsSnapshot rows across a date range — the
 * backfill/reconciliation path, complementing AnalyticsProjector's
 * real-time per-event re-aggregation (spec section 8). Two uses:
 * seeding history for a store that just installed analytics (or
 * re-deriving after a MetricCalculator bug fix), and the
 * `analytics:rebuild-snapshots` console command.
 */
final class AnalyticsSnapshotBuilder
{
    public function __construct(private readonly AnalyticsAggregator $aggregator) {}

    public function buildRange(string $storeId, Carbon $from, Carbon $to): void
    {
        $day = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($day->lte($end)) {
            $this->aggregator->aggregateDay($storeId, $day);
            $day = $day->copy()->addDay();
        }
    }

    public function buildRangeForAllStores(Carbon $from, Carbon $to): void
    {
        Store::query()->cursor()->each(fn (Store $store) => $this->buildRange($store->id, $from, $to));
    }
}
