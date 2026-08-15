<?php

namespace App\Domain\Search\Enums;

enum SearchRuleAction: string
{
    case Boost = 'boost';
    case Hide = 'hide';
}
