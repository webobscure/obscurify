<?php

namespace App\Domain\CustomerIntelligence\Enums;

enum CustomerTagAssignmentSource: string
{
    case Manual = 'manual';
    case System = 'system';
}
