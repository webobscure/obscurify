<?php

namespace App\Domain\Shipping\Enums;

enum ShippingMethodStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
