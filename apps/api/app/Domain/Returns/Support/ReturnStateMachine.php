<?php

namespace App\Domain\Returns\Support;

use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Exceptions\InvalidReturnTransitionException;

/**
 * The only place ReturnRequest status transitions are allowed to happen
 * from — mirrors FulfillmentStateMachine/ShipmentStateMachine. `approved`
 * always advances straight to `awaiting_return` in the same call
 * (ApproveReturn) without a separate endpoint, the same "no dedicated
 * endpoint for an automatic follow-on state" precedent
 * PackFulfillmentItems' auto-advance-to-`ready` already established — a
 * merchant approving a return has nothing further to decide before
 * waiting on the physical package. `completed`, `rejected`, and
 * `cancelled` are terminal.
 */
final class ReturnStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const array TRANSITIONS = [
        'requested' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['awaiting_return', 'cancelled'],
        'awaiting_return' => ['received', 'cancelled'],
        'received' => ['inspection', 'cancelled'],
        'inspection' => ['completed', 'cancelled'],
    ];

    public function canTransition(ReturnStatus $from, ReturnStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * @throws InvalidReturnTransitionException
     */
    public function guard(ReturnStatus $from, ReturnStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw InvalidReturnTransitionException::make($from, $to);
        }
    }
}
