<?php

namespace App\Domain\RussianCommerce\Enums;

/**
 * Spec section 16: refund/correction receipt architecture, prepared but
 * not issuable yet — `Refund` is a real, storable value (a receipt's
 * `correction_of_id` can reference the original), but no application
 * service creates one this milestone. See docs/architecture/fiscalization.md
 * "Future: refunds" for the documented flow.
 */
enum FiscalReceiptOperation: string
{
    case Sale = 'sale';
    case Refund = 'refund';
}
