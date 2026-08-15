<?php

namespace App\Domain\Notifications\Enums;

enum NotificationRecipientType: string
{
    case Customer = 'customer';
    case AdHoc = 'ad_hoc';
}
