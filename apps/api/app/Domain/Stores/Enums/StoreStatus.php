<?php

namespace App\Domain\Stores\Enums;

enum StoreStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
