<?php

namespace App\Domain\Webhooks\Enums;

enum WebhookSubscriptionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
