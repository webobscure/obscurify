<?php

namespace App\Domain\CustomerIntelligence\Enums;

enum CustomerSegmentStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
