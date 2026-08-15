<?php

namespace App\Domain\Analytics\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
