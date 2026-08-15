<?php

namespace App\Domain\Analytics\Support;

use App\Domain\Analytics\Models\AnalyticsSnapshot;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Orchestrates MetricCalculator across every known metric for one
 * (store, day), upserting the resulting AnalyticsSnapshot rows — the
 * only thing in this domain that writes to `analytics_snapshots`.
 *
 * Runs base metrics first, then derived ones (which read the base
 * metrics' just-computed values in memory, not from the database —
 * see MetricCalculator), then the one gauge metric last.
 *
 * Explicitly re-scopes TenantContext to `$storeId` itself, rather than
 * trusting an ambient scope — the same defensive pattern
 * DeliverWebhookJob/RunWorkflowExecutionJob use, since
 * BelongsToTenant's `creating()` hook forces `store_id` from whatever
 * TenantContext happens to be active at write time regardless of
 * `withoutGlobalScopes()`; without this, a future multi-store backfill
 * command that forgot to scope per store would silently write every
 * snapshot under one wrong tenant.
 */
final class AnalyticsAggregator
{
    /**
     * @var string[]
     */
    private const array BASE_METRIC_KEYS = [
        'gross_revenue',
        'order_count',
        'paid_order_count',
        'refund_count',
        'refund_amount',
        'return_count',
        'new_customers',
        'returning_customers',
        'lifetime_value',
        'top_products',
        'top_categories',
        'top_collections',
        'top_discounts',
        'top_shipping_methods',
        'conversion_rate',
        'search_count',
        'zero_result_search_count',
        'search_click_count',
    ];

    /**
     * @var string[]
     */
    private const array DERIVED_METRIC_KEYS = [
        'net_revenue',
        'average_order_value',
        'repeat_purchase_rate',
    ];

    public function __construct(
        private readonly MetricCalculator $calculator,
        private readonly TenantContext $tenantContext,
    ) {}

    public function aggregateDay(string $storeId, Carbon $day): void
    {
        $store = Store::query()->find($storeId);

        if ($store === null) {
            return;
        }

        $this->tenantContext->scope($store, function () use ($storeId, $day) {
            $baseValues = [];

            foreach (self::BASE_METRIC_KEYS as $metricKey) {
                $result = $this->calculator->calculateBase($metricKey, $storeId, $day);
                $baseValues[$metricKey] = $result;
                $this->upsert($storeId, $metricKey, $day, $result);
            }

            foreach (self::DERIVED_METRIC_KEYS as $metricKey) {
                $this->upsert($storeId, $metricKey, $day, $this->calculator->calculateDerived($metricKey, $baseValues));
            }

            $this->upsert($storeId, 'inventory_value', $day, [
                'value' => $this->calculator->calculateInventoryValueGauge(),
                'count' => null,
                'breakdown' => null,
            ]);
        });
    }

    /**
     * @param  array{value: int|null, count: int|null, breakdown: array<string, mixed>|null}  $result
     */
    /**
     * `updateOrCreate()` alone isn't atomic (SELECT then INSERT-or-
     * UPDATE) — two concurrent aggregation runs for the same
     * (store, metric, day) (e.g. two `outbox:process` workers, each
     * having claimed a different event that both land on today) could
     * both see "no row yet" and both attempt to INSERT, and the loser
     * hits the unique constraint. Caught and retried as a plain update
     * against the row the winner just created — the same
     * claim-or-retry shape as every other idempotent writer in this
     * codebase, just retrying an update instead of skipping.
     */
    private function upsert(string $storeId, string $metricKey, Carbon $day, array $result): void
    {
        $attributes = ['store_id' => $storeId, 'metric_key' => $metricKey, 'period_date' => $day->toDateString()];
        $values = ['value' => $result['value'], 'count' => $result['count'], 'breakdown' => $result['breakdown'], 'computed_at' => now()];

        try {
            AnalyticsSnapshot::withoutGlobalScopes()->updateOrCreate($attributes, $values);
        } catch (UniqueConstraintViolationException) {
            AnalyticsSnapshot::withoutGlobalScopes()->where($attributes)->update($values);
        }
    }
}
