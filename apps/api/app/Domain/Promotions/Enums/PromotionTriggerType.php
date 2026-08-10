<?php

namespace App\Domain\Promotions\Enums;

enum PromotionTriggerType: string
{
    case Automatic = 'automatic';
    case Code = 'code';
}
