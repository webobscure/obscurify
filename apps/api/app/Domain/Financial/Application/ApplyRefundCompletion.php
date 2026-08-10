<?php

namespace App\Domain\Financial\Application;

use App\Domain\Financial\Enums\LedgerAccount;
use App\Domain\Financial\Enums\LedgerDirection;
use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Financial\Models\FinancialEvent;
use App\Domain\Financial\Models\Refund;
use App\Domain\Financial\Support\LedgerLine;
use App\Domain\Financial\Support\RefundStateMachine;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\PaymentStateMachine;
use App\Shared\Commerce\Application\RecordOutboxEvent;

/**
 * The one place a refund's completion actually moves money on the books
 * (spec section 8's "Refund Request -> Provider Refund -> Webhook ->
 * Ledger -> Order Financial Status" pipeline, collapsed to its last two
 * steps) — mirrors CompleteFulfillment's role as "the one place stock
 * actually consumes," except in reverse and for money: Dr Revenue /
 * Cr Cash reverses the Dr Cash / Cr Revenue a payment capture posted.
 *
 * Called from two places: RequestRefund (manual refunds — no provider,
 * no webhook to wait for, so this runs synchronously in the same
 * request) and ProcessRefundWebhook (provider refunds — runs once the
 * provider confirms success). Both callers must already hold a row lock
 * on both $refund and the owning Payment.
 *
 * Idempotent by construction: if $refund is already `completed`, this is
 * a no-op — the same "check applied_at before doing it again" discipline
 * Returns\Application\CompleteReturn::applyDisposition() already
 * established, so a duplicate webhook delivery (already deduplicated one
 * layer earlier by RefundWebhookEvent's claim/poll, but defense in depth)
 * can never double-post ledger entries.
 */
final class ApplyRefundCompletion
{
    public function __construct(
        private readonly RefundStateMachine $refundStateMachine,
        private readonly PaymentStateMachine $paymentStateMachine,
        private readonly PostLedgerEntries $postLedgerEntries,
        private readonly RecomputeOrderFinancialStatus $recomputeOrderFinancialStatus,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Refund $lockedRefund, Payment $lockedPayment, Order $lockedOrder): Refund
    {
        if ($lockedRefund->status === RefundStatus::Completed) {
            return $lockedRefund;
        }

        $this->refundStateMachine->guard($lockedRefund->status, RefundStatus::Completed);

        $this->postLedgerEntries->handle(
            order: $lockedOrder,
            referenceType: Refund::class,
            referenceId: $lockedRefund->id,
            description: "Refund #{$lockedRefund->number}",
            currency: $lockedRefund->currency,
            lines: [
                new LedgerLine(LedgerAccount::Revenue, LedgerDirection::Debit, $lockedRefund->amount),
                new LedgerLine(LedgerAccount::Cash, LedgerDirection::Credit, $lockedRefund->amount),
            ],
        );

        $newRefundedAmount = $lockedPayment->refunded_amount + $lockedRefund->amount;
        $paymentTarget = $newRefundedAmount >= $lockedPayment->captured_amount
            ? PaymentStatus::Refunded
            : PaymentStatus::PartiallyRefunded;

        $this->paymentStateMachine->guard($lockedPayment->status, $paymentTarget);
        $lockedPayment->update([
            'refunded_amount' => $newRefundedAmount,
            'status' => $paymentTarget->value,
        ]);

        $lockedRefund->update([
            'status' => RefundStatus::Completed->value,
            'processed_at' => now(),
        ]);

        FinancialEvent::query()->create([
            'order_id' => $lockedOrder->id,
            'type' => 'refund_completed',
            'description' => "Refund #{$lockedRefund->number} completed.",
            'occurred_at' => now(),
        ]);

        FinancialEvent::query()->create([
            'order_id' => $lockedOrder->id,
            'type' => 'ledger_created',
            'description' => "Ledger entries posted for refund #{$lockedRefund->number}.",
            'occurred_at' => now(),
        ]);

        $this->recomputeOrderFinancialStatus->handle($lockedOrder);

        $this->recordOutboxEvent->handle('RefundCompleted', 'Refund', $lockedRefund->id, [
            'refund_id' => $lockedRefund->id,
            'payment_id' => $lockedPayment->id,
            'order_id' => $lockedOrder->id,
            'store_id' => $lockedRefund->store_id,
            'amount' => $lockedRefund->amount,
            'currency' => $lockedRefund->currency,
        ]);

        return $lockedRefund->fresh(['items']);
    }
}
