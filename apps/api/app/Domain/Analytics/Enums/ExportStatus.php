<?php

namespace App\Domain\Analytics\Enums;

enum ExportStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
