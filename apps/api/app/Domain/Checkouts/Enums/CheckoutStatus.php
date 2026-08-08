<?php

namespace App\Domain\Checkouts\Enums;

enum CheckoutStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
