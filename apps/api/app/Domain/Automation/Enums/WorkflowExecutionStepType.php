<?php

namespace App\Domain\Automation\Enums;

enum WorkflowExecutionStepType: string
{
    case Condition = 'condition';
    case Action = 'action';
}
