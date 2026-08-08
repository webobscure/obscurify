<?php

namespace App\Domain\Payments\Exceptions;

use App\Domain\Payments\Enums\PaymentStatus;
use RuntimeException;

final class InvalidPaymentTransitionException extends RuntimeException
{
    public static function make(PaymentStatus $from, PaymentStatus $to): self
    {
        return new self("Payment cannot transition from \"{$from->value}\" to \"{$to->value}\".");
    }
}
