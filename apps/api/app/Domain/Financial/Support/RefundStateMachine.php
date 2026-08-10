<?php

namespace App\Domain\Financial\Support;

use App\Domain\Financial\Enums\RefundStatus;
use App\Domain\Financial\Exceptions\InvalidRefundTransitionException;

/**
 * The only place Refund status transitions are allowed to happen from —
 * mirrors PaymentStateMachine/ReturnStateMachine. `requested -> completed`
 * (skipping `processing` entirely) is the manual-refund path (spec
 * section 11): no provider call means no async webhook to wait for, so
 * RequestRefund completes it synchronously in the same call.
 *
 * Deliberately stricter than PaymentStateMachine's own
 * `processing -> cancelled` precedent: once a provider-backed refund has
 * been submitted (`processing`), it cannot be cancelled from here —
 * unlike a customer still sitting on a fake payment page, a refund
 * already sent to a provider has no interactive session to back out of;
 * only `requested` (before submission) is cancellable.
 */
final class RefundStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const array TRANSITIONS = [
        'requested' => ['processing', 'completed', 'cancelled'],
        'processing' => ['completed', 'failed'],
    ];

    public function canTransition(RefundStatus $from, RefundStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * @throws InvalidRefundTransitionException
     */
    public function guard(RefundStatus $from, RefundStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw InvalidRefundTransitionException::make($from, $to);
        }
    }
}
