<?php

namespace App\Domain\Shipping\Support;

use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Shipping\Exceptions\InvalidShipmentTransitionException;

/**
 * The only place Shipment status transitions are allowed to happen from —
 * mirrors PaymentStateMachine. `delivered -> in_transit` (or any other
 * regression out of a terminal state) is deliberately NOT supported (spec
 * section 13/25): a provider "correction" after delivery has no
 * documented policy yet, so delivered/failed/cancelled are all terminal —
 * a webhook reporting one is recorded as a TrackingEvent (the delivery
 * happened, that's a fact worth keeping) but never applied as a state
 * transition (see ProcessShippingWebhook).
 *
 * `delivery_exception` is deliberately recoverable, not terminal — a real
 * carrier's "recipient not home" / "address issue" is retried, not a dead
 * end: it can return to `out_for_delivery` (redelivery attempt) or
 * `in_transit` (returned to a sorting facility for another attempt), or
 * still resolve to `failed`/`cancelled` if the carrier gives up.
 */
final class ShipmentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const array TRANSITIONS = [
        'pending' => ['ready', 'created', 'failed', 'cancelled'],
        'ready' => ['created', 'failed', 'cancelled'],
        'created' => ['accepted', 'in_transit', 'failed', 'cancelled'],
        'accepted' => ['in_transit', 'failed', 'cancelled'],
        'in_transit' => ['out_for_delivery', 'delivered', 'delivery_exception', 'failed', 'cancelled'],
        'out_for_delivery' => ['delivered', 'delivery_exception', 'failed', 'cancelled'],
        'delivery_exception' => ['out_for_delivery', 'in_transit', 'failed', 'cancelled'],
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
