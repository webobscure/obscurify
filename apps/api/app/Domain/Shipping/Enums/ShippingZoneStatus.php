<?php

namespace App\Domain\Shipping\Enums;

enum ShippingZoneStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
