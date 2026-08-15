<?php

namespace App\Domain\Automation\Enums;

enum WorkflowConditionBoolean: string
{
    case And = 'and';
    case Or = 'or';
}
