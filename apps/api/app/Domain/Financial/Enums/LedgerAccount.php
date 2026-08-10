<?php

namespace App\Domain\Financial\Enums;

/**
 * A minimal two-account chart — this milestone's ledger is deliberately
 * not full GAAP accounting (spec section 20: no invoices, no accounting
 * export, no taxes). `Cash` represents money actually held/moved;
 * `Revenue` represents recognized income. A payment capture is Dr Cash /
 * Cr Revenue; a refund completion reverses it: Dr Revenue / Cr Cash.
 */
enum LedgerAccount: string
{
    case Cash = 'cash';
    case Revenue = 'revenue';
}
