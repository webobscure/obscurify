<?php

namespace App\Domain\Notifications\Enums;

/**
 * A Notification's aggregate rollup across all of its
 * NotificationDeliveries — set by NotificationDispatcher at creation
 * and re-derived whenever a delivery reaches a terminal state (see
 * RecalculateNotificationStatus).
 */
enum NotificationStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case PartiallyDelivered = 'partially_delivered';
}
