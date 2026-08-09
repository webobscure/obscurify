<?php

namespace App\Domain\Shipping\Support;

use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Shipping\Exceptions\InvalidShipmentTransitionException;

/**
 * The only place Shipment status transitions are allowed to happen from —
 * mirrors PaymentStateMachine. `delivered -> in_transit` is deliberately
 * NOT supported (spec section 25): a provider "correction" after delivery
 * has no documented policy yet, so delivered/failed/cancelled are all
 * terminal.
 */
final class ShipmentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const array TRANSITIONS = [
        'pending' => ['ready', 'created', 'failed', 'cancelled'],
        'ready' => ['created', 'failed', 'cancelled'],
        'created' => ['in_transit', 'failed', 'cancelled'],
        'in_transit' => ['delivered', 'failed', 'cancelled'],
    ];

    public function canTransition(ShipmentStatus $from, ShipmentStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * @throws InvalidShipmentTransitionException
     */
    public function guard(ShipmentStatus $from, ShipmentStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw InvalidShipmentTransitionException::make($from, $to);
        }
    }
}
