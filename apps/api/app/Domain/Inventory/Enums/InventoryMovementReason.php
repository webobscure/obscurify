<?php

namespace App\Domain\Inventory\Enums;

enum InventoryMovementReason: string
{
    case ManualAdjustment = 'manual_adjustment';
    case InitialStock = 'initial_stock';
    case Import = 'import';
    case ReturnStock = 'return';
    case Damage = 'damage';
    case Correction = 'correction';

    // Fulfillment Core (Milestone 7) — see docs/architecture/fulfillment.md
    // §"Inventory movements" for exactly when each fires and why
    // FulfillmentAllocated/ReservationReleased carry a zero on_hand delta
    // (they're reserved-bookkeeping events, not physical stock changes)
    // while FulfillmentCompleted/ShipmentCancelled are real on_hand deltas.
    case FulfillmentAllocated = 'fulfillment_allocated';
    case FulfillmentCompleted = 'fulfillment_completed';
    case ReservationReleased = 'reservation_released';
    case ShipmentCancelled = 'shipment_cancelled';

    // Returns & Reverse Logistics (Milestone 8) — see
    // docs/architecture/returns.md "Inventory integration" for exactly
    // when each fires. Deliberately new cases rather than reusing the
    // generic ReturnStock/Damage above: those are unscoped manual-
    // adjustment reasons, while these are always written by CompleteReturn/
    // ReceiveReturn with a ReturnItem reference. ReturnReceived carries a
    // zero on_hand delta (the package physically arrived — inventory
    // changes only happen after inspection, per spec). ReturnRestocked is
    // the one real positive on_hand delta. ReturnDamaged/ReturnDiscarded
    // are also zero-delta: damaged or discarded units must never
    // automatically become sellable stock.
    case ReturnReceived = 'return_received';
    case ReturnRestocked = 'return_restocked';
    case ReturnDamaged = 'return_damaged';
    case ReturnDiscarded = 'return_discarded';
}
