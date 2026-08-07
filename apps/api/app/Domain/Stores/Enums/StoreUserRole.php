<?php

namespace App\Domain\Stores\Enums;

enum StoreUserRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Manager = 'manager';
}
