<?php

namespace App\Domain\Analytics\Support;

use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes one metric's value for one (store, day) — always from
 * `analytics_events` (never a commerce table), with one deliberate,
 * narrow exception for gauge metrics (currently only `inventory_value`,
 * a point-in-time stock reading with no natural "flow" event to derive
 * it from) — see docs/adr/026-analytics-platform.md.
 *
 * Split into two phases the caller (AnalyticsAggregator) runs in order:
 * `calculateBase()` for metrics computable independently from that
 * day's events, and `calculateDerived()` for metrics computed from
 * *other* metrics' already-written values for the same day (net
 * revenue, average order value, repeat purchase rate) — passed in as
 * `$baseValues` rather than re-read from the database, since they were
 * just written moments ago in the same aggregation run.
 */
final class MetricCalculator
{
    private const int LEADERBOARD_SIZE = 10;

    /**
     * @return array{value: int|null, count: int|null, breakdown: array<string, mixed>|null}
     */
    public function calculateBase(string $metricKey, string $storeId, Carbon $day): array
    {
        return match ($metricKey) {
            'gross_revenue' => $this->sum($storeId, $day, 'OrderPaymentConfirmed'),
            'order_count' => $this->count($storeId, $day, 'OrderCreated'),
            'paid_order_count' => $this->count($storeId, $day, 'OrderPaymentConfirmed'),
            'refund_count' => $this->count($storeId, $day, 'RefundCompleted'),
            'refund_amount' => $this->sum($storeId, $day, 'RefundCompleted'),
            'return_count' => $this->count($storeId, $day, 'ReturnCompleted'),
            'new_customers' => $this->count($storeId, $day, 'CustomerCreated'),
            'returning_customers' => $this->returningCustomers($storeId, $day),
            'lifetime_value' => $this->averageLifetimeValue($storeId, $day),
            'top_products' => $this->leaderboard($storeId, $day, fn (array $lineItem) => [['key' => $lineItem['product_id'], 'label' => $lineItem['product_title'] ?? $lineItem['product_id'], 'value' => $lineItem['amount'] ?? 0]]),
            'top_categories' => $this->leaderboard($storeId, $day, fn (array $lineItem) => array_map(fn ($categoryId) => ['key' => $categoryId, 'label' => $categoryId, 'value' => $lineItem['amount'] ?? 0], $lineItem['category_ids'] ?? [])),
            'top_collections' => $this->leaderboard($storeId, $day, fn (array $lineItem) => array_map(fn ($collectionId) => ['key' => $collectionId, 'label' => $collectionId, 'value' => $lineItem['amount'] ?? 0], $lineItem['collection_ids'] ?? [])),
            'top_discounts' => $this->discountLeaderboard($storeId, $day),
            'top_shipping_methods' => $this->shippingLeaderboard($storeId, $day),
            'conversion_rate' => ['value' => null, 'count' => null, 'breakdown' => null],
            default => ['value' => null, 'count' => null, 'breakdown' => null],
        };
    }

    /**
     * @param  array<string, array{value: int|null, count: int|null, breakdown: array<string, mixed>|null}>  $baseValues  keyed by metric_key, this same day
     * @return array{value: int|null, count: int|null, breakdown: array<string, mixed>|null}
     */
    public function calculateDerived(string $metricKey, array $baseValues): array
    {
        return match ($metricKey) {
            'net_revenue' => $this->netRevenue($baseValues),
            'average_order_value' => $this->averageOrderValue($baseValues),
            'repeat_purchase_rate' => $this->repeatPurchaseRate($baseValues),
            default => ['value' => null, 'count' => null, 'breakdown' => null],
        };
    }

    /**
     * Inventory Value (spec section 3) — a point-in-time gauge, not a
     * flow: current on-hand stock × cost, across every tracked item.
     * Deliberately reads InventoryLevel/ProductVariant directly (the
     * one place outside AnalyticsProjector this domain touches a
     * commerce table) since there is no event that captures "total
     * stock value" as a delta — see docs/adr/026-analytics-platform.md.
     * Items with no `cost_amount` set contribute 0, not null — an
     * honest reflection of "merchant hasn't priced this item's cost
     * yet," not a computation failure.
     */
    public function calculateInventoryValueGauge(): int
    {
        return (int) InventoryLevel::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_levels.inventory_item_id')
            ->join('product_variants', 'product_variants.id', '=', 'inventory_items.product_variant_id')
            ->selectRaw('COALESCE(SUM(inventory_levels.on_hand * COALESCE(product_variants.cost_amount, 0)), 0) as total')
            ->value('total');
    }

    /**
     * @return array{value: int, count: int, breakdown: null}
     */
    private function sum(string $storeId, Carbon $day, string $eventType): array
    {
        $sum = (int) $this->dayQuery($storeId, $day, $eventType)->sum('amount');
        $count = $this->dayQuery($storeId, $day, $eventType)->count();

        return ['value' => $sum, 'count' => $count, 'breakdown' => null];
    }

    /**
     * @return array{value: int, count: int, breakdown: null}
     */
    private function count(string $storeId, Carbon $day, string $eventType): array
    {
        $count = $this->dayQuery($storeId, $day, $eventType)->count();

        return ['value' => $count, 'count' => $count, 'breakdown' => null];
    }

    /**
     * @return array{value: int, count: int, breakdown: null}
     */
    private function returningCustomers(string $storeId, Carbon $day): array
    {
        $customerIds = $this->dayQuery($storeId, $day, 'OrderCreated')
            ->where('payload->is_first_order', false)
            ->distinct()
            ->pluck('customer_id');

        return ['value' => $customerIds->count(), 'count' => $customerIds->count(), 'breakdown' => null];
    }

    /**
     * Average, across customers who had a paid order that day, of that
     * customer's all-time total spend recorded in analytics_events —
     * self-contained within Analytics' own table (see class docblock).
     *
     * @return array{value: int, count: int, breakdown: null}
     */
    private function averageLifetimeValue(string $storeId, Carbon $day): array
    {
        $customerIds = $this->dayQuery($storeId, $day, 'OrderPaymentConfirmed')->distinct()->pluck('customer_id')->filter();

        if ($customerIds->isEmpty()) {
            return ['value' => 0, 'count' => 0, 'breakdown' => null];
        }

        $totalsPerCustomer = AnalyticsEvent::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('event_type', 'OrderPaymentConfirmed')
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, SUM(amount) as total')
            ->groupBy('customer_id')
            ->pluck('total');

        $average = $totalsPerCustomer->isEmpty() ? 0 : (int) round($totalsPerCustomer->sum() / $totalsPerCustomer->count());

        return ['value' => $average, 'count' => $customerIds->count(), 'breakdown' => null];
    }

    /**
     * @param  callable(array<string, mixed>): list<array{key: string, label: string, value: int}>  $extract
     * @return array{value: null, count: int, breakdown: array<string, array{label: string, value: int}>}
     */
    private function leaderboard(string $storeId, Carbon $day, callable $extract): array
    {
        $totals = collect();

        $this->dayQuery($storeId, $day, 'OrderPaymentConfirmed')->get(['payload'])->each(function (AnalyticsEvent $event) use ($extract, $totals) {
            foreach (($event->payload['line_items'] ?? []) as $lineItem) {
                foreach ($extract($lineItem) as $entry) {
                    $existing = $totals->get($entry['key'], ['label' => $entry['label'], 'value' => 0]);
                    $totals->put($entry['key'], ['label' => $entry['label'], 'value' => $existing['value'] + $entry['value']]);
                }
            }
        });

        return ['value' => null, 'count' => $totals->count(), 'breakdown' => $this->topN($totals)];
    }

    /**
     * @return array{value: null, count: int, breakdown: array<string, array{label: string, value: int}>}
     */
    private function discountLeaderboard(string $storeId, Carbon $day): array
    {
        $totals = collect();

        $this->dayQuery($storeId, $day, 'OrderPaymentConfirmed')->get(['payload'])->each(function (AnalyticsEvent $event) use ($totals) {
            foreach (($event->payload['discounts'] ?? []) as $discount) {
                $key = $discount['discount_code_id'] ?? $discount['promotion_id'] ?? 'unknown';
                $existing = $totals->get($key, ['label' => $discount['label'] ?? $key, 'value' => 0]);
                $totals->put($key, ['label' => $existing['label'], 'value' => $existing['value'] + ($discount['amount'] ?? 0)]);
            }
        });

        return ['value' => null, 'count' => $totals->count(), 'breakdown' => $this->topN($totals)];
    }

    /**
     * @return array{value: null, count: int, breakdown: array<string, array{label: string, value: int}>}
     */
    private function shippingLeaderboard(string $storeId, Carbon $day): array
    {
        $totals = collect();

        $this->dayQuery($storeId, $day, 'OrderPaymentConfirmed')->get(['payload'])->each(function (AnalyticsEvent $event) use ($totals) {
            $shipping = $event->payload['shipping'] ?? null;

            if ($shipping === null) {
                return;
            }

            $key = $shipping['label'] ?? 'unknown';
            $existing = $totals->get($key, ['label' => $key, 'value' => 0]);
            $totals->put($key, ['label' => $key, 'value' => $existing['value'] + ($shipping['amount'] ?? 0)]);
        });

        return ['value' => null, 'count' => $totals->count(), 'breakdown' => $this->topN($totals)];
    }

    /**
     * @param  array<string, array{value: int|null, count: int|null, breakdown: array<string, mixed>|null}>  $baseValues
     * @return array{value: int, count: null, breakdown: null}
     */
    private function netRevenue(array $baseValues): array
    {
        $gross = $baseValues['gross_revenue']['value'] ?? 0;
        $refunds = $baseValues['refund_amount']['value'] ?? 0;

        return ['value' => max(0, $gross - $refunds), 'count' => null, 'breakdown' => null];
    }

    /**
     * @param  array<string, array{value: int|null, count: int|null, breakdown: array<string, mixed>|null}>  $baseValues
     * @return array{value: int, count: null, breakdown: null}
     */
    private function averageOrderValue(array $baseValues): array
    {
        $gross = $baseValues['gross_revenue']['value'] ?? 0;
        $paidOrders = $baseValues['paid_order_count']['value'] ?? 0;

        return ['value' => $paidOrders > 0 ? (int) round($gross / $paidOrders) : 0, 'count' => null, 'breakdown' => null];
    }

    /**
     * Stored as basis points (spec-consistent with CustomerMetric.return_rate_bps
     * from Milestone 18) — divide by 100 for a percent.
     *
     * @param  array<string, array{value: int|null, count: int|null, breakdown: array<string, mixed>|null}>  $baseValues
     * @return array{value: int, count: null, breakdown: null}
     */
    private function repeatPurchaseRate(array $baseValues): array
    {
        $returning = $baseValues['returning_customers']['value'] ?? 0;
        $orders = $baseValues['order_count']['value'] ?? 0;

        return ['value' => $orders > 0 ? (int) round($returning / $orders * 10000) : 0, 'count' => null, 'breakdown' => null];
    }

    /**
     * @return Builder<AnalyticsEvent>
     */
    private function dayQuery(string $storeId, Carbon $day, string $eventType): Builder
    {
        return AnalyticsEvent::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('event_type', $eventType)
            ->whereBetween('occurred_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
    }

    /**
     * @param  Collection<string, array{label: string, value: int}>  $totals
     * @return array<string, array{label: string, value: int}>
     */
    private function topN(Collection $totals): array
    {
        return $totals->sortByDesc('value')->take(self::LEADERBOARD_SIZE)->all();
    }
}
