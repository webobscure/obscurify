<?php

namespace App\Domain\CustomerIntelligence\Enums;

enum CustomerGroupType: string
{
    case Manual = 'manual';
    case Dynamic = 'dynamic';
    case Protected = 'protected';
}
