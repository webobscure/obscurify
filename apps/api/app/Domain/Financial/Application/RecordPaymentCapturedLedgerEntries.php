<?php

namespace App\Domain\Financial\Application;

use App\Domain\Financial\Enums\LedgerAccount;
use App\Domain\Financial\Enums\LedgerDirection;
use App\Domain\Financial\Models\FinancialEvent;
use App\Domain\Financial\Support\LedgerLine;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;

/**
 * Posts the Dr Cash / Cr Revenue entries for a captured payment (spec
 * section 5: "Every payment and refund creates entries"). Called from
 * ProcessPaymentWebhook::applyTransition() when a Payment first reaches
 * `paid`, inside the same transaction and under the same row lock that
 * write already holds — this class does no locking or idempotency of its
 * own, same division of responsibility as PostLedgerEntries.
 */
final class RecordPaymentCapturedLedgerEntries
{
    public function __construct(
        private readonly PostLedgerEntries $postLedgerEntries,
    ) {}

    public function handle(Order $lockedOrder, Payment $lockedPayment): void
    {
        $this->postLedgerEntries->handle(
            order: $lockedOrder,
            referenceType: Payment::class,
            referenceId: $lockedPayment->id,
            description: 'Payment captured',
            currency: $lockedPayment->currency,
            lines: [
                new LedgerLine(LedgerAccount::Cash, LedgerDirection::Debit, $lockedPayment->captured_amount),
                new LedgerLine(LedgerAccount::Revenue, LedgerDirection::Credit, $lockedPayment->captured_amount),
            ],
        );

        FinancialEvent::query()->create([
            'order_id' => $lockedOrder->id,
            'type' => 'payment_captured',
            'description' => 'Payment captured.',
            'occurred_at' => now(),
        ]);

        FinancialEvent::query()->create([
            'order_id' => $lockedOrder->id,
            'type' => 'ledger_created',
            'description' => 'Ledger entries posted for captured payment.',
            'occurred_at' => now(),
        ]);
    }
}
