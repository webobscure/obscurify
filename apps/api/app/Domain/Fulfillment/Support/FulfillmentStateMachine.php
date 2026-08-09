<?php

namespace App\Domain\Fulfillment\Support;

use App\Domain\Fulfillment\Enums\FulfillmentStatus;
use App\Domain\Fulfillment\Exceptions\InvalidFulfillmentTransitionException;

/**
 * The only place Fulfillment status transitions are allowed to happen from
 * — mirrors ShipmentStateMachine/PaymentStateMachine. `packing` can go
 * straight to `ready` (PackFulfillmentItems does this automatically once
 * every item is fully packed) without an explicit intermediate call, but
 * no state is ever skipped by a caller going through the guarded actions
 * (spec section 3: "do not skip intermediate warehouse states") — pending
 * must reach allocated before picking, etc. `completed` and `cancelled`
 * are terminal.
 */
final class FulfillmentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const array TRANSITIONS = [
        'pending' => ['allocated', 'cancelled'],
        'allocated' => ['picking', 'cancelled'],
        'picking' => ['packing', 'cancelled'],
        'packing' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
    ];

    public function canTransition(FulfillmentStatus $from, FulfillmentStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * @throws InvalidFulfillmentTransitionException
     */
    public function guard(FulfillmentStatus $from, FulfillmentStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw InvalidFulfillmentTransitionException::make($from, $to);
        }
    }
}
