<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Enums\ReportStatus;
use App\Domain\Analytics\Enums\ReportType;
use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Analytics\Models\Report;
use App\Domain\Analytics\Models\SavedReport;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Builds a Report's result set entirely from `analytics_events` — never
 * a commerce table (spec sections 2/12) — capped at MAX_ROWS per run,
 * a deliberate scope simplification ("architecture" over "data
 * warehouse"; see docs/adr/026-analytics-platform.md) rather than
 * building true streaming/pagination for arbitrarily large exports.
 */
final class RunReport
{
    private const int MAX_ROWS = 1000;

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     */
    public function handle(ReportType $type, array $filters, array $columns = [], ?SavedReport $savedReport = null): Report
    {
        $report = Report::query()->create([
            'saved_report_id' => $savedReport?->id,
            'report_type' => $type->value,
            'filters' => $filters,
            'columns' => $columns,
            'status' => ReportStatus::Pending->value,
        ]);

        try {
            $rows = $this->buildRows($type, $filters);

            $report->update([
                'status' => ReportStatus::Completed->value,
                'result' => $rows,
                'row_count' => count($rows),
                'generated_at' => now(),
            ]);
        } catch (Throwable $e) {
            $report->update([
                'status' => ReportStatus::Failed->value,
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);
        }

        return $report->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function buildRows(ReportType $type, array $filters): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        return match ($type) {
            ReportType::Orders => $this->simpleReport($from, $to, 'OrderCreated', fn (AnalyticsEvent $e) => [
                'order_id' => $e->aggregate_id, 'number' => $e->payload['number'] ?? null, 'email' => $e->payload['email'] ?? null,
                'customer_id' => $e->customer_id, 'amount' => $e->amount, 'currency' => $e->currency,
                'status' => $e->payload['order_status'] ?? null, 'occurred_at' => $e->occurred_at->toIso8601String(),
            ]),
            ReportType::Payments => $this->simpleReport($from, $to, 'OrderPaymentConfirmed', fn (AnalyticsEvent $e) => [
                'order_id' => $e->aggregate_id, 'number' => $e->payload['number'] ?? null, 'amount' => $e->amount,
                'currency' => $e->currency, 'occurred_at' => $e->occurred_at->toIso8601String(),
            ]),
            ReportType::Returns => $this->simpleReport($from, $to, 'ReturnCompleted', fn (AnalyticsEvent $e) => [
                'return_id' => $e->aggregate_id, 'number' => $e->payload['number'] ?? null, 'order_id' => $e->payload['order_id'] ?? null,
                'status' => $e->payload['status'] ?? null, 'occurred_at' => $e->occurred_at->toIso8601String(),
            ]),
            ReportType::Shipping => $this->simpleReport($from, $to, 'ShipmentDelivered', fn (AnalyticsEvent $e) => [
                'shipment_id' => $e->aggregate_id, 'order_id' => $e->payload['order_id'] ?? null,
                'status' => $e->payload['status'] ?? null, 'occurred_at' => $e->occurred_at->toIso8601String(),
            ]),
            ReportType::Promotions => $this->simpleReport($from, $to, 'PromotionApplied', fn (AnalyticsEvent $e) => [
                'checkout_id' => $e->aggregate_id, 'promotion_id' => $e->payload['promotion_id'] ?? null,
                'code' => $e->payload['code'] ?? null, 'discount_amount' => $e->amount, 'occurred_at' => $e->occurred_at->toIso8601String(),
            ]),
            ReportType::Inventory => $this->simpleReport($from, $to, 'InventoryChanged', fn (AnalyticsEvent $e) => [
                'inventory_item_id' => $e->payload['inventory_item_id'] ?? null, 'location_id' => $e->payload['location_id'] ?? null,
                'quantity_delta' => $e->payload['quantity_delta'] ?? null, 'on_hand' => $e->payload['on_hand'] ?? null,
                'reason' => $e->payload['reason'] ?? null, 'occurred_at' => $e->occurred_at->toIso8601String(),
            ]),
            ReportType::AutomationExecutions => $this->automationExecutionsReport($from, $to),
            ReportType::Products => $this->productsReport($from, $to),
            ReportType::Customers => $this->customersReport($from, $to),
        };
    }

    /**
     * @param  callable(AnalyticsEvent): array<string, mixed>  $map
     * @return list<array<string, mixed>>
     */
    private function simpleReport(Carbon $from, Carbon $to, string $eventType, callable $map): array
    {
        return AnalyticsEvent::query()
            ->where('event_type', $eventType)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map($map)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function automationExecutionsReport(Carbon $from, Carbon $to): array
    {
        return AnalyticsEvent::query()
            ->whereIn('event_type', ['WorkflowExecuted', 'WorkflowExecutionFailed'])
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (AnalyticsEvent $e) => [
                'workflow_execution_id' => $e->payload['workflow_execution_id'] ?? null,
                'workflow_id' => $e->payload['workflow_id'] ?? null,
                'status' => $e->payload['status'] ?? null,
                'attempts' => $e->payload['attempts'] ?? null,
                'occurred_at' => $e->occurred_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productsReport(Carbon $from, Carbon $to): array
    {
        $totals = collect();

        AnalyticsEvent::query()
            ->where('event_type', 'OrderPaymentConfirmed')
            ->whereBetween('occurred_at', [$from, $to])
            ->limit(self::MAX_ROWS)
            ->get(['payload'])
            ->each(function (AnalyticsEvent $event) use ($totals) {
                foreach (($event->payload['line_items'] ?? []) as $lineItem) {
                    $key = $lineItem['product_id'];
                    $existing = $totals->get($key, ['product_id' => $key, 'product_title' => $lineItem['product_title'] ?? $key, 'quantity' => 0, 'revenue' => 0]);
                    $totals->put($key, [
                        'product_id' => $key,
                        'product_title' => $existing['product_title'],
                        'quantity' => $existing['quantity'] + ($lineItem['quantity'] ?? 0),
                        'revenue' => $existing['revenue'] + ($lineItem['amount'] ?? 0),
                    ]);
                }
            });

        return $totals->sortByDesc('revenue')->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customersReport(Carbon $from, Carbon $to): array
    {
        $totals = collect();

        AnalyticsEvent::query()
            ->where('event_type', 'OrderPaymentConfirmed')
            ->whereBetween('occurred_at', [$from, $to])
            ->whereNotNull('customer_id')
            ->limit(self::MAX_ROWS)
            ->get(['customer_id', 'amount'])
            ->each(function (AnalyticsEvent $event) use ($totals) {
                $existing = $totals->get($event->customer_id, ['customer_id' => $event->customer_id, 'order_count' => 0, 'total_spent' => 0]);
                $totals->put($event->customer_id, [
                    'customer_id' => $event->customer_id,
                    'order_count' => $existing['order_count'] + 1,
                    'total_spent' => $existing['total_spent'] + ($event->amount ?? 0),
                ]);
            });

        return $totals->sortByDesc('total_spent')->values()->all();
    }
}
