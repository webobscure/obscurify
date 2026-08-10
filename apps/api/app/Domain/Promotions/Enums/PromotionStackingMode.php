<?php

namespace App\Domain\Promotions\Enums;

enum PromotionStackingMode: string
{
    case Stackable = 'stackable';
    case Exclusive = 'exclusive';
}
