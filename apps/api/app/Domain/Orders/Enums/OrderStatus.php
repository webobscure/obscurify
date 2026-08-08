<?php

namespace App\Domain\Orders\Enums;

enum OrderStatus: string
{
    case Open = 'open';
    case Cancelled = 'cancelled';
    case Closed = 'closed';
}
