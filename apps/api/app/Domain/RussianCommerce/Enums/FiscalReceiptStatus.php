<?php

namespace App\Domain\RussianCommerce\Enums;

/**
 * Spec section 7 — deliberately separate from PaymentStatus (spec:
 * "Payment success should not directly mean fiscalization success").
 */
enum FiscalReceiptStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Fiscalized = 'fiscalized';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
