<?php

namespace App\Domain\Promotions\Enums;

enum DiscountApplicationTarget: string
{
    case Order = 'order';
    case Shipping = 'shipping';
    case LineItem = 'line_item';
}
