<?php

namespace App\Domain\Apps\Enums;

enum AppTokenType: string
{
    case Access = 'access';
    case Refresh = 'refresh';
}
