<?php

namespace App\Domain\Promotions\Enums;

enum DiscountCodeStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
