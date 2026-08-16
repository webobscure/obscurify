<?php

namespace App\Domain\RussianCommerce\Enums;

/**
 * Spec section 8 — the customer-facing payment channel for an order,
 * stored on `Payment.payment_method`. Provider-neutral by design: which
 * gateway actually processes a `bank_card`/`sbp` payment is
 * `Payment.provider`'s job, not this enum's.
 */
enum RussianPaymentMethod: string
{
    case BankCard = 'bank_card';
    case Sbp = 'sbp';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Credit = 'credit';
}
