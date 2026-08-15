<?php

namespace App\Domain\Automation\Enums;

enum WorkflowVariableType: string
{
    case String = 'string';
    case Number = 'number';
    case Boolean = 'boolean';
    case Date = 'date';
    case Enum = 'enum';
    case Collection = 'collection';
}
