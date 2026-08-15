<?php

namespace App\Console\Commands;

use App\Domain\CustomerIntelligence\Application\CaptureCustomerSnapshot;
use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\Customers\Models\Customer;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Background recalculation (spec section 7) — a full, from-scratch
 * recompute of every customer's metrics, independent of the incremental
 * event-driven path (RecomputeCustomerMetrics called directly from
 * CompleteCheckout etc.), which only ever touches the one customer an
 * event just happened to concern. This command is what actually
 * *guarantees* every customer's metrics are eventually correct even if
 * an incremental call was ever missed, and it's the only thing that
 * writes CustomerSnapshot rows (the "Customer Timeline" trend data —
 * see CaptureCustomerSnapshot).
 *
 * Same `withoutGlobalScopes()` + `TenantContext::scope()` per-row pattern
 * as ProcessOutboxEventsCommand/PublishScheduledBlogPostsCommand — no
 * scheduler wiring here either (see those commands' own docblocks on why
 * that's deliberately out of scope for the milestone that adds the
 * command itself, an ops concern).
 */
final class RecomputeCustomerMetricsCommand extends Command
{
    protected $signature = 'customer-intelligence:recompute-metrics {--snapshot : Also capture a CustomerSnapshot for each customer}';

    protected $description = 'Recomputes every customer\'s metrics from scratch across every store, optionally capturing a snapshot.';

    public function handle(
        TenantContext $tenantContext,
        RecomputeCustomerMetrics $recomputeCustomerMetrics,
        CaptureCustomerSnapshot $captureCustomerSnapshot,
    ): int {
        $takeSnapshot = (bool) $this->option('snapshot');
        $processed = 0;

        Customer::withoutGlobalScopes()
            ->select(['id', 'store_id'])
            ->orderBy('id')
            ->chunkById(200, function ($customers) use ($tenantContext, $recomputeCustomerMetrics, $captureCustomerSnapshot, $takeSnapshot, &$processed) {
                $storesById = Store::query()
                    ->whereIn('id', $customers->pluck('store_id')->unique())
                    ->get()
                    ->keyBy('id');

                foreach ($customers as $customer) {
                    $store = $storesById->get($customer->store_id);

                    if ($store === null) {
                        continue;
                    }

                    $tenantContext->scope($store, function () use ($customer, $recomputeCustomerMetrics, $captureCustomerSnapshot, $takeSnapshot) {
                        $metric = $recomputeCustomerMetrics->handle($customer->id);

                        if ($takeSnapshot) {
                            $captureCustomerSnapshot->handle($metric);
                        }
                    });

                    $processed++;
                }
            });

        $this->info("Recomputed metrics for {$processed} customer(s)".($takeSnapshot ? ', with snapshots.' : '.'));

        return self::SUCCESS;
    }
}
