<?php

namespace App\Domain\Apps\Enums;

enum AppStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
