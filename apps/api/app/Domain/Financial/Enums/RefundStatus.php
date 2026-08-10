<?php

namespace App\Domain\Financial\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
