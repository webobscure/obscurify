<?php

namespace App\Domain\Stores\Enums;

enum StoreUserStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';
}
