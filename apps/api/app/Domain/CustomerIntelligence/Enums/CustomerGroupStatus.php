<?php

namespace App\Domain\CustomerIntelligence\Enums;

enum CustomerGroupStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
