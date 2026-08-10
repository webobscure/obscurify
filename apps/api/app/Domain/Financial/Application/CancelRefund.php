<?php

namespace App\Domain\Financial\Application;

use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Financial\Models\FinancialEvent;
use App\Domain\Financial\Models\Refund;
use App\Domain\Financial\Support\RefundStateMachine;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Only reachable from `requested` (see RefundStateMachine's docblock for
 * why `processing` cannot be cancelled here) — no ledger effect, since
 * nothing was ever posted for a refund that never left `requested`.
 */
final class CancelRefund
{
    public function __construct(
        private readonly RefundStateMachine $stateMachine,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(Refund $refund): Refund
    {
        return DB::transaction(function () use ($refund) {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            $this->stateMachine->guard($locked->status, RefundStatus::Cancelled);

            $locked->update([
                'status' => RefundStatus::Cancelled->value,
                'processed_at' => now(),
            ]);

            FinancialEvent::query()->create([
                'order_id' => $locked->order_id,
                'type' => 'refund_cancelled',
                'description' => "Refund #{$locked->number} cancelled.",
                'occurred_at' => now(),
            ]);

            $this->recordOutboxEvent->handle('RefundCancelled', 'Refund', $locked->id, [
                'refund_id' => $locked->id,
                'order_id' => $locked->order_id,
                'store_id' => $locked->store_id,
            ]);

            return $locked->fresh(['items']);
        });
    }
}
