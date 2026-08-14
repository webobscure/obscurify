<?php

namespace App\Domain\Apps\Enums;

enum InstalledAppStatus: string
{
    case Active = 'active';
    case Uninstalled = 'uninstalled';
}
