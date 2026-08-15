<?php

namespace App\Domain\Financial\Application;

use App\Domain\CustomerIntelligence\Application\RecomputeCustomerMetrics;
use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Shared\Commerce\Application\RecordOutboxEvent;

/**
 * Order.financial_status must be derived, not an arbitrary mutable flag
 * (spec section 6) — recomputed from scratch from every Payment on the
 * Order each time, the same "recompute rather than increment" discipline
 * CompleteFulfillment::refreshOrderFulfillmentStatus already established
 * for Order.fulfillment_status. Called after every event that could move
 * it: a payment reaching `paid` (ProcessPaymentWebhook) and a refund
 * completing (ApplyRefundCompletion).
 *
 * Caller must already hold a row lock on $order (this class does not
 * lock it itself — it's always called from inside a transaction that
 * already locked the Order for its own reasons).
 */
final class RecomputeOrderFinancialStatus
{
    public function __construct(
        private readonly RecordOutboxEvent $recordOutboxEvent,
        private readonly RecomputeCustomerMetrics $recomputeCustomerMetrics,
    ) {}

    public function handle(Order $lockedOrder): void
    {
        // Covers both "OrderPaid" and every refund-driven change spec
        // section 8 lists as a metrics trigger — captured/refunded
        // totals can shift here even when the *categorical* financial_status
        // doesn't (e.g. a second partial refund staying within
        // PartiallyRefunded), so this always runs, ahead of both early
        // returns below. See CompleteCheckout's identical call for why
        // this is a direct call rather than a subscription.
        if ($lockedOrder->customer_id !== null) {
            $this->recomputeCustomerMetrics->handle($lockedOrder->customer_id);
        }

        // Voided is never set by this recompute — it represents an
        // explicit merchant override this milestone doesn't build a
        // trigger for, and must never be silently reversed by a payment/
        // refund event landing afterward.
        if ($lockedOrder->financial_status === FinancialStatus::Voided) {
            return;
        }

        $totalCaptured = (int) Payment::query()
            ->where('order_id', $lockedOrder->id)
            ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value])
            ->sum('captured_amount');

        $totalRefunded = (int) Payment::query()
            ->where('order_id', $lockedOrder->id)
            ->sum('refunded_amount');

        $status = match (true) {
            $totalCaptured <= 0 => FinancialStatus::Pending,
            $totalRefunded >= $totalCaptured => FinancialStatus::Refunded,
            $totalRefunded > 0 => FinancialStatus::PartiallyRefunded,
            default => FinancialStatus::Paid,
        };

        if ($lockedOrder->financial_status === $status) {
            return;
        }

        $lockedOrder->update(['financial_status' => $status->value]);

        $this->recordOutboxEvent->handle('OrderFinancialStatusChanged', 'Order', $lockedOrder->id, [
            'order_id' => $lockedOrder->id,
            'store_id' => $lockedOrder->store_id,
            'financial_status' => $status->value,
        ]);
    }
}
