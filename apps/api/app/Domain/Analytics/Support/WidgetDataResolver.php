<?php

namespace App\Domain\Analytics\Support;

use App\Domain\Analytics\Enums\MetricCalculation;
use App\Domain\Analytics\Enums\TimeDimension;
use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Analytics\Models\AnalyticsSnapshot;
use App\Domain\Analytics\Models\MetricDefinition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single read path every dashboard widget (and report/drill-down)
 * goes through — always `analytics_snapshots` (or, for drill-down,
 * `analytics_events`), never a commerce table, and never a synchronous
 * calculation over anything but Analytics' own already-aggregated data
 * (spec sections 2/12).
 */
final class WidgetDataResolver
{
    /**
     * Which raw event_type a metric's drill-down (spec section 7)
     * shows — the event type that actually drives that metric's value.
     *
     * @var array<string, string>
     */
    private const array DRILL_DOWN_EVENT_TYPES = [
        'gross_revenue' => 'OrderPaymentConfirmed',
        'net_revenue' => 'OrderPaymentConfirmed',
        'paid_order_count' => 'OrderPaymentConfirmed',
        'average_order_value' => 'OrderPaymentConfirmed',
        'order_count' => 'OrderCreated',
        'returning_customers' => 'OrderCreated',
        'repeat_purchase_rate' => 'OrderCreated',
        'refund_count' => 'RefundCompleted',
        'refund_amount' => 'RefundCompleted',
        'return_count' => 'ReturnCompleted',
        'new_customers' => 'CustomerCreated',
        'lifetime_value' => 'OrderPaymentConfirmed',
        'top_products' => 'OrderPaymentConfirmed',
        'top_categories' => 'OrderPaymentConfirmed',
        'top_collections' => 'OrderPaymentConfirmed',
        'top_discounts' => 'OrderPaymentConfirmed',
        'top_shipping_methods' => 'OrderPaymentConfirmed',
    ];

    public function __construct(private readonly TimeRangeResolver $timeRangeResolver) {}

    /**
     * @return array{metric_key: string, from: string, to: string, total: int|null, series: list<array{date: string, value: int|null, count: int|null}>, breakdown: array<string, array{label: string, value: int}>|null}
     */
    public function resolve(string $metricKey, TimeDimension $dimension, ?Carbon $customFrom = null, ?Carbon $customTo = null): array
    {
        [$from, $to] = $this->timeRangeResolver->resolve($dimension, $customFrom, $customTo);

        $snapshots = AnalyticsSnapshot::query()
            ->where('metric_key', $metricKey)
            ->whereBetween('period_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('period_date')
            ->get();

        $series = $snapshots->map(fn (AnalyticsSnapshot $snapshot) => [
            'date' => $snapshot->period_date->toDateString(),
            'value' => $snapshot->value,
            'count' => $snapshot->count,
        ])->all();

        $definition = MetricDefinition::query()->where('key', $metricKey)->first();
        $isGauge = $definition?->calculation === MetricCalculation::Gauge;

        // A gauge is a point-in-time reading (e.g. current inventory
        // value) — summing it across days would double-count the same
        // stock; the latest day in range is the meaningful "total."
        $total = $isGauge ? $snapshots->last()?->value : $snapshots->sum('value');

        return [
            'metric_key' => $metricKey,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total' => $total,
            'series' => $series,
            'breakdown' => $this->mergeBreakdowns($snapshots),
        ];
    }

    /**
     * Spec section 7: "Merchant can open details from every dashboard
     * widget" — the underlying raw AnalyticsEvent rows behind a
     * metric's value for the same range, paginated.
     *
     * @return LengthAwarePaginator<int, AnalyticsEvent>
     */
    public function drillDown(string $metricKey, TimeDimension $dimension, ?Carbon $customFrom, ?Carbon $customTo, int $perPage = 25): LengthAwarePaginator
    {
        [$from, $to] = $this->timeRangeResolver->resolve($dimension, $customFrom, $customTo);
        $eventType = self::DRILL_DOWN_EVENT_TYPES[$metricKey] ?? null;

        $query = AnalyticsEvent::query()->whereBetween('occurred_at', [$from, $to]);

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        return $query->orderByDesc('occurred_at')->paginate($perPage);
    }

    /**
     * @param  Collection<int, AnalyticsSnapshot>  $snapshots
     * @return array<string, array{label: string, value: int}>|null
     */
    private function mergeBreakdowns(Collection $snapshots): ?array
    {
        $merged = collect();
        $sawAny = false;

        foreach ($snapshots as $snapshot) {
            if ($snapshot->breakdown === null) {
                continue;
            }

            $sawAny = true;

            foreach ($snapshot->breakdown as $key => $entry) {
                $existing = $merged->get($key, ['label' => $entry['label'], 'value' => 0]);
                $merged->put($key, ['label' => $existing['label'], 'value' => $existing['value'] + $entry['value']]);
            }
        }

        if (! $sawAny) {
            return null;
        }

        return $merged->sortByDesc('value')->take(10)->all();
    }
}
