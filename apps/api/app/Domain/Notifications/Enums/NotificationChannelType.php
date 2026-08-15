<?php

namespace App\Domain\Notifications\Enums;

/**
 * Spec section 3. Future channels register the same way a future
 * NotificationProvider does — through NotificationProviderRegistry, not
 * a schema change here.
 */
enum NotificationChannelType: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Push = 'push';
    case InApp = 'in_app';
    case Webhook = 'webhook';
}
