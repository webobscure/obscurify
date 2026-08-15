<?php

namespace App\Domain\Notifications\Enums;

/**
 * Spec section 7. `Scheduled` is registered for forward-compatibility
 * only — no scheduling engine exists yet (spec section 14: "No
 * scheduling engine"), matching the same "catalog-only, not wired up
 * yet" convention Milestone 19 used for `OrderCancelled`.
 */
enum NotificationTriggerSource: string
{
    case PlatformEvent = 'platform_event';
    case Automation = 'automation';
    case Admin = 'admin';
    case AppsSdk = 'apps_sdk';
    case Scheduled = 'scheduled';
}
