<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\CustomerIntelligence\Models\CustomerSnapshot;

/**
 * Writes one append-only historical copy of a CustomerMetric row (spec
 * section 7) — called by the background recomputation command, never
 * from the incremental event-driven path (RecomputeCustomerMetrics),
 * which only ever touches the live CustomerMetric row. Snapshots are
 * what powers the admin's "Customer Timeline" trend view (spec
 * section 12); the live CustomerMetric row alone has no history.
 */
final class CaptureCustomerSnapshot
{
    public function handle(CustomerMetric $metric): CustomerSnapshot
    {
        return CustomerSnapshot::query()->create([
            'customer_id' => $metric->customer_id,
            'metrics' => [
                'total_spent_amount' => $metric->total_spent_amount,
                'average_order_value_amount' => $metric->average_order_value_amount,
                'order_count' => $metric->order_count,
                'refund_count' => $metric->refund_count,
                'return_count' => $metric->return_count,
                'return_rate_bps' => $metric->return_rate_bps,
                'lifetime_value_amount' => $metric->lifetime_value_amount,
                'currency' => $metric->currency,
            ],
            'captured_at' => now(),
        ]);
    }
}
