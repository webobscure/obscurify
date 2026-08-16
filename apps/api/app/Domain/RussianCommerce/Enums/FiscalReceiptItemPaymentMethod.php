<?php

namespace App\Domain\RussianCommerce\Enums;

/**
 * Spec section 6 — the 54-FZ line-item "способ расчёта" (how this line
 * is being settled), not to be confused with RussianPaymentMethod
 * (which payment *channel* the customer used for the whole order —
 * card/SBP/transfer/cash/credit). A single order paid in full by card
 * still reports each line as FullPayment here; Prepayment/Advance/Credit
 * exist for future deposit/layaway/installment flows this milestone
 * only models, doesn't implement.
 */
enum FiscalReceiptItemPaymentMethod: string
{
    case FullPayment = 'full_payment';
    case Prepayment = 'prepayment';
    case Advance = 'advance';
    case Credit = 'credit';
}
