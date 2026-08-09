<?php

namespace App\Domain\Shipping\Enums;

/**
 * Deliberately provider-neutral (spec section 16) — a real provider's own
 * status vocabulary (e.g. CDEK's numeric codes) must be translated into
 * these by that provider's parseWebhook(), never leak through as-is.
 */
enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Created = 'created';
    case Accepted = 'accepted';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryException = 'delivery_exception';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
