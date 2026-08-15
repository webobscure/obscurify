<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Domain\Notifications\Jobs\SendNotificationDeliveryJob;
use App\Domain\Notifications\Models\NotificationDelivery;
use Illuminate\Console\Command;

/**
 * Re-dispatches every `failed` NotificationDelivery whose backoff
 * window (`next_retry_at`) has passed — the notification-domain
 * sibling of RetryFailedWebhookDeliveriesCommand/AutomationRetryFailedCommand.
 * `exhausted`/`suppressed` rows are deliberately excluded, the same
 * "dead letter is not retried by this command" convention every other
 * retry-failed command in this codebase follows.
 */
class RetryFailedNotificationDeliveriesCommand extends Command
{
    protected $signature = 'notifications:retry-failed';

    protected $description = 'Re-dispatch failed notification deliveries whose backoff window has passed';

    public function handle(): int
    {
        $ids = NotificationDelivery::withoutGlobalScopes()
            ->where('status', NotificationDeliveryStatus::Failed->value)
            ->where('next_retry_at', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            SendNotificationDeliveryJob::dispatch($id);
        }

        $this->info("Re-dispatched {$ids->count()} notification delivery(ies).");

        return self::SUCCESS;
    }
}
