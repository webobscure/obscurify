<?php

namespace App\Domain\Customers\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
