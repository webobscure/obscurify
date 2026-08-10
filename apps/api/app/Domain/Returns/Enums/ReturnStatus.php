<?php

namespace App\Domain\Returns\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case AwaitingReturn = 'awaiting_return';
    case Received = 'received';
    case Inspection = 'inspection';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
