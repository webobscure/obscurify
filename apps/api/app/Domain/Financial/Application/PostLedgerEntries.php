<?php

namespace App\Domain\Financial\Application;

use App\Domain\Financial\Enums\LedgerDirection;
use App\Domain\Financial\Models\LedgerEntry;
use App\Domain\Financial\Models\LedgerTransaction;
use App\Domain\Financial\Support\LedgerLine;
use App\Domain\Orders\Models\Order;
use LogicException;

/**
 * The one place a LedgerTransaction + its LedgerEntry rows are ever
 * written (spec section 5: "Every payment and refund creates entries" /
 * "History must never be modified") — every other Application class in
 * this domain calls into this one rather than writing ledger_transactions/
 * ledger_entries directly, so the balance invariant below is enforced in
 * exactly one place.
 *
 * Callers must already hold whatever row lock makes their own write
 * idempotent (Payment/Refund) — this class has no idempotency of its own,
 * same division of responsibility as InventoryMovement::create() calls
 * throughout Fulfillment/Shipping/Returns.
 */
final class PostLedgerEntries
{
    /**
     * @param  list<LedgerLine>  $lines
     */
    public function handle(Order $order, string $referenceType, string $referenceId, string $description, string $currency, array $lines): LedgerTransaction
    {
        $this->assertBalanced($lines);

        $transaction = LedgerTransaction::query()->create([
            'order_id' => $order->id,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'occurred_at' => now(),
        ]);

        foreach ($lines as $line) {
            LedgerEntry::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'account' => $line->account->value,
                'direction' => $line->direction->value,
                'currency' => $currency,
                'amount' => $line->amount,
            ]);
        }

        return $transaction->fresh(['entries']);
    }

    /**
     * @param  list<LedgerLine>  $lines
     */
    private function assertBalanced(array $lines): void
    {
        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            if ($line->amount <= 0) {
                throw new LogicException('Ledger line amounts must be positive — direction carries the sign, not the amount.');
            }

            if ($line->direction === LedgerDirection::Debit) {
                $debits += $line->amount;
            } else {
                $credits += $line->amount;
            }
        }

        if ($debits !== $credits) {
            throw new LogicException("Unbalanced ledger transaction: debits ({$debits}) != credits ({$credits}).");
        }
    }
}
