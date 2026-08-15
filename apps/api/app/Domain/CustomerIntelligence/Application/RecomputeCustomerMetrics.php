<?php

namespace App\Domain\CustomerIntelligence\Application;

use App\Domain\CustomerIntelligence\Models\CustomerMetric;
use App\Domain\Customers\Models\Customer;
use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Financial\Models\Refund;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;

/**
 * The one place customer metrics are (re)computed — always from scratch
 * off Orders/Payments/Refunds/Returns, never incremented, the same
 * "recompute rather than increment" discipline
 * RecomputeOrderFinancialStatus already established for
 * Order.financial_status. Called directly, synchronously, from inside
 * the same transaction as every triggering event (spec section 8:
 * OrderCreated/OrderPaid/RefundCompleted/CustomerCreated/CustomerUpdated/
 * ReturnCompleted) — this codebase has no internal pub/sub mechanism to
 * "subscribe" to (see docs/adr/024-customer-intelligence.md); the
 * precedent for "domain A reacts to domain B's completion" is Financial
 * Ledger calling `RecomputeOrderFinancialStatus` directly, which this
 * mirrors.
 *
 * "Total spent" is net of refunds (captured minus refunded, the same
 * definition the ledger's Dr Cash/Cr Revenue posting already implies);
 * "average order value" is gross (average Order.total_amount) —
 * deliberately different bases, both real, both useful. "Lifetime
 * value" is an alias of total spent for now — true LTV forecasting is
 * out of scope ("No predictive analytics", spec section 16).
 */
final class RecomputeCustomerMetrics
{
    public function __construct(
        private readonly RefreshCustomerSegmentMemberships $refreshCustomerSegmentMemberships,
        private readonly AutoTagCustomer $autoTagCustomer,
    ) {}

    public function handle(string $customerId): CustomerMetric
    {
        return DB::transaction(function () use ($customerId) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);

            $metric = CustomerMetric::query()->where('customer_id', $customerId)->lockForUpdate()->first();

            $orders = Order::query()->where('customer_id', $customerId)->get(['id', 'total_amount', 'currency', 'created_at']);
            $orderIds = $orders->pluck('id');
            $orderCount = $orders->count();

            $totalCaptured = (int) Payment::query()
                ->whereIn('order_id', $orderIds)
                ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value])
                ->sum('captured_amount');

            $totalRefunded = (int) Payment::query()->whereIn('order_id', $orderIds)->sum('refunded_amount');

            $totalSpent = max(0, $totalCaptured - $totalRefunded);
            $averageOrderValue = $orderCount > 0 ? (int) round($orders->sum('total_amount') / $orderCount) : 0;

            $refundCount = (int) Refund::query()
                ->whereIn('order_id', $orderIds)
                ->where('status', RefundStatus::Completed->value)
                ->count();

            $returnCount = (int) ReturnRequest::query()
                ->whereIn('order_id', $orderIds)
                ->where('status', ReturnStatus::Completed->value)
                ->count();

            $returnRateBps = $orderCount > 0 ? (int) round($returnCount / $orderCount * 10000) : 0;

            $attributes = [
                'total_spent_amount' => $totalSpent,
                'average_order_value_amount' => $averageOrderValue,
                'order_count' => $orderCount,
                'refund_count' => $refundCount,
                'return_count' => $returnCount,
                'return_rate_bps' => $returnRateBps,
                'lifetime_value_amount' => $totalSpent,
                'currency' => $orders->first()?->currency,
                'first_order_at' => $orders->min('created_at'),
                'last_order_at' => $orders->max('created_at'),
                'computed_at' => now(),
            ];

            if ($metric === null) {
                $metric = CustomerMetric::query()->create(['customer_id' => $customerId, ...$attributes]);
            } else {
                $metric->update($attributes);
            }

            $this->autoTagCustomer->handle($customer, $metric);
            $this->refreshCustomerSegmentMemberships->handle($customer, $metric);

            return $metric;
        });
    }
}
