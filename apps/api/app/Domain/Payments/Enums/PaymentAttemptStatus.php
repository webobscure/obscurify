<?php

namespace App\Domain\Payments\Enums;

/**
 * Status of the *attempt to initiate* a provider payment (the
 * createPayment() call itself) — not the resulting payment's own
 * lifecycle, which is tracked on Payment/PaymentTransaction instead.
 */
enum PaymentAttemptStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
