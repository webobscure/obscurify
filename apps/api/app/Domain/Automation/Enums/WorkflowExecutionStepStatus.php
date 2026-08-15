<?php

namespace App\Domain\Automation\Enums;

enum WorkflowExecutionStepStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Waiting = 'waiting';
}
