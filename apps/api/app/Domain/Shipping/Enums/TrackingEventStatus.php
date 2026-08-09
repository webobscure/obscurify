<?php

namespace App\Domain\Shipping\Enums;

/**
 * Mirrors ShipmentStatus's vocabulary for the statuses that are genuinely
 * point-in-time events worth an append-only history row. Not identical to
 * ShipmentStatus 1:1 (e.g. there's no "pending"/"ready" tracking event —
 * those precede the shipment existing with the provider at all).
 */
enum TrackingEventStatus: string
{
    case Created = 'created';
    case Accepted = 'accepted';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryException = 'delivery_exception';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
