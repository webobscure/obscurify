<?php

namespace App\Domain\Automation\Enums;

enum DelayType: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';
    case UntilDate = 'until_date';
    case UntilEvent = 'until_event';
}
