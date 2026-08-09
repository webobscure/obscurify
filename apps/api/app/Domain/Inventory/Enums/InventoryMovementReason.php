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
}
