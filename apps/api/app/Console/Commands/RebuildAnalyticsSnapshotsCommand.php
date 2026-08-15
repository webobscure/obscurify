<?php

namespace App\Console\Commands;

use App\Domain\Analytics\Support\AnalyticsSnapshotBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Backfills/rebuilds AnalyticsSnapshot rows for a date range — for
 * seeding a store's history or re-deriving after a MetricCalculator
 * change. Not wired into Laravel's scheduler, matching this codebase's
 * standing convention for every other operational command
 * (outbox:process, automation:resume-delayed, ...) — run externally on
 * a cron; day-to-day freshness comes from AnalyticsProjector's
 * real-time per-event re-aggregation, not from this command.
 */
class RebuildAnalyticsSnapshotsCommand extends Command
{
    protected $signature = 'analytics:rebuild-snapshots
        {--store= : Store ID — omit to rebuild for every store}
        {--from= : Start date (Y-m-d), defaults to 30 days ago}
        {--to= : End date (Y-m-d), defaults to today}';

    protected $description = 'Rebuild analytics snapshots for a date range';

    public function handle(AnalyticsSnapshotBuilder $builder): int
    {
        $from = $this->option('from') !== null ? Carbon::parse($this->option('from')) : now()->subDays(30);
        $to = $this->option('to') !== null ? Carbon::parse($this->option('to')) : now();
        $storeId = $this->option('store');

        if ($storeId !== null) {
            $builder->buildRange($storeId, $from, $to);
        } else {
            $builder->buildRangeForAllStores($from, $to);
        }

        $this->info("Rebuilt analytics snapshots from {$from->toDateString()} to {$to->toDateString()}.");

        return self::SUCCESS;
    }
}
