<?php

namespace App\Domain\Promotions\Enums;

enum PromotionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
