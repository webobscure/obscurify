<?php

namespace App\Domain\Automation\Enums;

enum WorkflowExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case DeadLetter = 'dead_letter';
}
