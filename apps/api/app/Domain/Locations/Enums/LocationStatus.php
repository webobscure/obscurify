<?php

namespace App\Domain\Locations\Enums;

enum LocationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
