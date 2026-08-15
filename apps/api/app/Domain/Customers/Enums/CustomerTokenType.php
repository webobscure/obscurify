<?php

namespace App\Domain\Customers\Enums;

enum CustomerTokenType: string
{
    case Access = 'access';
    case Refresh = 'refresh';
}
