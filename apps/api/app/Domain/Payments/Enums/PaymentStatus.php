<?php

namespace App\Domain\Payments\Enums;

/**
 * Deliberately its own enum, not reused as Order's FinancialStatus:
 * a Payment can be `processing` while the Order stays `pending`, and an
 * Order's financial_status only ever moves in response to a *verified*
 * Payment transition (see ProcessPaymentWebhook) — collapsing the two
 * would blur that boundary.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}
