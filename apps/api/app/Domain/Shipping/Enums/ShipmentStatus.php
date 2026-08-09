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
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
