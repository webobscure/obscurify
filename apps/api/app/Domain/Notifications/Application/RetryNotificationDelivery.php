<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Domain\Notifications\Jobs\SendNotificationDeliveryJob;
use App\Domain\Notifications\Models\NotificationDelivery;
use RuntimeException;

/**
 * Manual re-send from the admin "Failed Deliveries"/"Retry Queue" UI —
 * bypasses the `next_retry_at` backoff wait (the merchant is explicitly
 * asking for it now), but only for a delivery that's actually retryable;
 * `succeeded` and `suppressed` are terminal-by-design, and `exhausted`
 * has already spent its MAX_ATTEMPTS budget (re-queuing it would defeat
 * the point of a dead letter).
 */
final class RetryNotificationDelivery
{
    public function handle(NotificationDelivery $delivery): NotificationDelivery
    {
        if ($delivery->status !== NotificationDeliveryStatus::Failed) {
            throw new RuntimeException('Only a failed delivery can be manually retried.');
        }

        SendNotificationDeliveryJob::dispatch($delivery->id);

        return $delivery->fresh();
    }
}
