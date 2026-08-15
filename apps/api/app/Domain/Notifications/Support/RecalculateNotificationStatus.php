<?php

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Models\Notification;

/**
 * Re-derives a Notification's aggregate `status` from its deliveries'
 * current statuses — called after every delivery attempt (see
 * SendNotificationDeliveryJob) and once right after NotificationDispatcher
 * creates them, since the `sync` queue driver this test suite runs on
 * means a delivery can already be terminal before dispatch() returns.
 */
final class RecalculateNotificationStatus
{
    public function handle(Notification $notification): void
    {
        $statuses = $notification->deliveries()->withoutGlobalScopes()->pluck('status');

        // Enum instances don't implement __toString(), so Collection::intersect()
        // (which delegates to array_intersect()'s implicit string casts) fatals
        // on them — contains()/in_array() with strict comparison works fine,
        // since PHP enum cases are canonical singletons.
        $inFlight = fn (NotificationDeliveryStatus $status) => in_array($status, [NotificationDeliveryStatus::Pending, NotificationDeliveryStatus::Sending], true);

        if ($statuses->isEmpty() || $statuses->contains($inFlight)) {
            $notification->update(['status' => NotificationStatus::Pending->value]);

            return;
        }

        $succeeded = $statuses->filter(fn (NotificationDeliveryStatus $status) => $status === NotificationDeliveryStatus::Succeeded)->count();
        $hardFailed = $statuses->filter(fn (NotificationDeliveryStatus $status) => in_array($status, [NotificationDeliveryStatus::Failed, NotificationDeliveryStatus::Exhausted], true))->count();

        $status = match (true) {
            $succeeded > 0 && $hardFailed > 0 => NotificationStatus::PartiallyDelivered,
            $hardFailed > 0 => NotificationStatus::Failed,
            default => NotificationStatus::Delivered,
        };

        $notification->update(['status' => $status->value]);
    }
}
