<?php

namespace App\Domain\Payments\Enums;

enum PaymentSessionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
