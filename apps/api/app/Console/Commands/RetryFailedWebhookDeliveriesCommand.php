<?php

namespace App\Console\Commands;

use App\Domain\Webhooks\Enums\WebhookDeliveryStatus;
use App\Domain\Webhooks\Jobs\DeliverWebhookJob;
use App\Domain\Webhooks\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * Re-dispatches every `failed` WebhookDelivery whose backoff window
 * (`next_retry_at`) has passed — see DeliverWebhookJob's own docblock
 * for why retry scheduling lives here instead of Laravel's queue-level
 * $tries/backoff. Intended to run on a short recurring schedule (e.g.
 * every minute) the same way `outbox:process` does; not wired into
 * Laravel's scheduler in this milestone since no other command in this
 * codebase is either — see docs/architecture/webhooks.md.
 */
class RetryFailedWebhookDeliveriesCommand extends Command
{
    protected $signature = 'webhooks:retry-failed';

    protected $description = 'Re-dispatch failed webhook deliveries whose backoff window has passed';

    public function handle(): int
    {
        $ids = WebhookDelivery::withoutGlobalScopes()
            ->where('status', WebhookDeliveryStatus::Failed->value)
            ->where('next_retry_at', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            DeliverWebhookJob::dispatch($id);
        }

        $this->info("Re-dispatched {$ids->count()} webhook delivery(ies).");

        return self::SUCCESS;
    }
}
